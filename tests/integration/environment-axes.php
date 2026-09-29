<?php

use FluentMail\App\Hooks\Handlers\AdminMenuHandler;
use FluentMail\App\Services\Reporting;

return function () {
    /** Prove the requested process-local timezone is the clock under test. */
    $assertTimezoneAxis = function () {
        $requested = getenv('FSMTP_TEST_GMT_OFFSET');
        if ($requested === false || $requested === '') {
            return;
        }

        FsmtpTest::assertSame((float)$requested, (float)get_option('gmt_offset'), 'active gmt_offset axis');
        FsmtpTest::assertSame('', (string)get_option('timezone_string'), 'numeric-offset timezone axis');
    };

    /** Run hard-coded production log queries against an isolated real table. */
    $redirectFixture = function ($table, callable $callback) {
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            return $callback();
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
        }
    };

    /** Convert dashboard widget table rows to title => [sent, failed]. */
    $widgetRows = function ($html) {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $rows = [];
        foreach ($document->getElementsByTagName('tbody')->item(0)->getElementsByTagName('tr') as $row) {
            $cells = $row->getElementsByTagName('td');
            $rows[trim($cells->item(0)->textContent)] = [
                (int)trim($cells->item(1)->textContent),
                (int)trim($cells->item(2)->textContent),
            ];
        }
        return $rows;
    };

    /** Enable strict modes for this connection and return its prior value. */
    $enableStrictSql = function () {
        global $wpdb;
        $before = (string)$wpdb->get_var('SELECT @@SESSION.sql_mode');
        $modes = array_values(array_unique(array_merge(
            array_filter(array_map('trim', explode(',', $before))),
            ['ONLY_FULL_GROUP_BY', 'STRICT_TRANS_TABLES']
        )));
        $result = $wpdb->query($wpdb->prepare('SET SESSION sql_mode = %s', implode(',', $modes)));
        if ($result === false) {
            throw new RuntimeException('Could not enable strict SQL modes: ' . $wpdb->last_error);
        }
        return $before;
    };

    $restoreSqlMode = function ($mode) {
        global $wpdb;
        $result = $wpdb->query($wpdb->prepare('SET SESSION sql_mode = %s', $mode));
        if ($result === false) {
            throw new RuntimeException('Could not restore SQL mode: ' . $wpdb->last_error);
        }
    };

    /** Create strict-mode fixtures and restore both table and session exactly. */
    $withStrictFixture = function (callable $callback) use ($enableStrictSql, $restoreSqlMode) {
        $beforeMode = $enableStrictSql();
        $table = FsmtpFactory::emailLogTable(false, true);
        $rows = [
            ['status' => 'sent', 'subject' => 'alpha', 'created_at' => '2025-01-06 10:00:00'],
            ['status' => 'sent', 'subject' => 'alpha', 'created_at' => '2025-01-07 11:00:00'],
            ['status' => 'failed', 'subject' => 'beta', 'created_at' => '2025-03-01 12:00:00'],
            ['status' => 'sent', 'subject' => 'gamma', 'created_at' => '2025-08-01 13:00:00'],
        ];
        foreach ($rows as $row) {
            FsmtpFactory::insertLog($table, $row);
        }

        try {
            return $callback($table);
        } finally {
            FsmtpFactory::dropTable($table);
            $restoreSqlMode($beforeMode);
        }
    };

    FsmtpTest::case('log pruning uses the site-local retention cutoff at non-UTC offsets', function () use ($assertTimezoneAxis) {
        global $wpdb;
        $assertTimezoneAxis();

        $table = FsmtpFactory::emailLogTable(false, true);
        $days = 14;
        $cutoff = current_datetime()->sub(new DateInterval('P' . $days . 'D'));
        $oldId = FsmtpFactory::insertLog($table, [
            'subject' => 'site-local-old',
            'created_at' => $cutoff->modify('-2 hours')->format('Y-m-d H:i:s'),
        ]);
        $newId = FsmtpFactory::insertLog($table, [
            'subject' => 'site-local-new',
            'created_at' => $cutoff->modify('+2 hours')->format('Y-m-d H:i:s'),
        ]);

        try {
            $deleted = FsmtpFactory::loggerForTable($table)->deleteLogsOlderThan($days);
            $remaining = array_map('intval', $wpdb->get_col("SELECT id FROM `{$table}` ORDER BY id"));

            FsmtpTest::assertSame(1, $deleted, 'site-local expired row count');
            FsmtpTest::assertSame([$newId], $remaining, 'site-local retained row IDs');
            FsmtpTest::assert(!in_array($oldId, $remaining, true), 'expired row survived the site-local cutoff');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('dashboard widget counters use the site-local midnight boundary', function () use (
        $assertTimezoneAxis,
        $redirectFixture,
        $widgetRows
    ) {
        $assertTimezoneAxis();
        $table = FsmtpFactory::emailLogTable(false, true);
        $today = current_datetime()->setTime(0, 0, 0);
        FsmtpFactory::insertLog($table, [
            'status' => 'sent',
            'created_at' => $today->modify('-30 minutes')->format('Y-m-d H:i:s'),
        ]);
        FsmtpFactory::insertLog($table, [
            'status' => 'sent',
            'created_at' => $today->modify('+30 minutes')->format('Y-m-d H:i:s'),
        ]);
        FsmtpFactory::insertLog($table, [
            'status' => 'failed',
            'created_at' => $today->modify('+45 minutes')->format('Y-m-d H:i:s'),
        ]);

        try {
            $html = $redirectFixture($table, function () {
                $handler = new AdminMenuHandler(fluentMail());
                $method = new ReflectionMethod($handler, 'getDashboardWidgetHtml');
                $method->setAccessible(true);
                return $method->invoke($handler);
            });
            $rows = $widgetRows($html);

            FsmtpTest::assertSame([1, 1], isset($rows['Today']) ? $rows['Today'] : null, 'site-local Today counters');
            FsmtpTest::assertSame([2, 1], isset($rows['All Time']) ? $rows['All Time'] : null, 'isolated all-time counters');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('report chart buckets rows by their site-local calendar day', function () use (
        $assertTimezoneAxis,
        $redirectFixture
    ) {
        $assertTimezoneAxis();
        $table = FsmtpFactory::emailLogTable(false, true);
        $today = current_datetime()->setTime(0, 0, 0);
        $previous = $today->modify('-1 day');
        FsmtpFactory::insertLog($table, ['created_at' => $previous->setTime(23, 30)->format('Y-m-d H:i:s')]);
        FsmtpFactory::insertLog($table, ['created_at' => $today->setTime(0, 30)->format('Y-m-d H:i:s')]);
        FsmtpFactory::insertLog($table, ['created_at' => $today->setTime(12, 0)->format('Y-m-d H:i:s')]);

        try {
            $stats = $redirectFixture($table, function () use ($previous, $today) {
                return (new Reporting())->getSendingStats(
                    $previous->format('Y-m-d'),
                    $today->format('Y-m-d')
                );
            });

            FsmtpTest::assertSame(['sent' => 1, 'failed' => 0], isset($stats[$previous->format('Y-m-d')]) ? $stats[$previous->format('Y-m-d')] : null, 'previous-day chart bucket');
            FsmtpTest::assertSame(['sent' => 2, 'failed' => 0], isset($stats[$today->format('Y-m-d')]) ? $stats[$today->format('Y-m-d')] : null, 'today chart bucket');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('report chart excludes rows immediately outside the requested range', function () use ($redirectFixture) {
        $table = FsmtpFactory::emailLogTable(false, true);
        FsmtpFactory::insertLog($table, ['created_at' => '2025-01-05 23:59:59']);
        FsmtpFactory::insertLog($table, ['created_at' => '2025-01-07 12:00:00']);
        FsmtpFactory::insertLog($table, ['created_at' => '2025-01-11 00:00:01']);

        try {
            $stats = $redirectFixture($table, function () {
                return (new Reporting())->getSendingStats('2025-01-06', '2025-01-10');
            });

            FsmtpTest::assert(!isset($stats['2025-01-05']), 'row immediately before the report range contributed a bucket');
            FsmtpTest::assert(!isset($stats['2025-01-11']), 'row immediately after the report range contributed a bucket');
            FsmtpTest::assertSame(1, array_sum(array_column($stats, 'sent')), 'out-of-range rows contributed to the report count');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('report chart counts sent and failed apart and leaves pending out of both', function () use ($redirectFixture) {
        $table = FsmtpFactory::emailLogTable(false, true);
        FsmtpFactory::insertLog($table, ['status' => 'sent', 'created_at' => '2025-01-06 09:00:00']);
        FsmtpFactory::insertLog($table, ['status' => 'sent', 'created_at' => '2025-01-06 10:00:00']);
        FsmtpFactory::insertLog($table, ['status' => 'failed', 'created_at' => '2025-01-06 11:00:00']);
        FsmtpFactory::insertLog($table, ['status' => 'pending', 'created_at' => '2025-01-06 12:00:00']);
        FsmtpFactory::insertLog($table, ['status' => 'failed', 'created_at' => '2025-01-07 09:00:00']);

        try {
            $stats = $redirectFixture($table, function () {
                return (new Reporting())->getSendingStats('2025-01-06', '2025-01-07');
            });

            FsmtpTest::assertSame(
                ['sent' => 2, 'failed' => 1],
                isset($stats['2025-01-06']) ? $stats['2025-01-06'] : null,
                'per-status bucket with a pending row in it'
            );
            FsmtpTest::assertSame(
                ['sent' => 0, 'failed' => 1],
                isset($stats['2025-01-07']) ? $stats['2025-01-07'] : null,
                'per-status bucket with only a failure in it'
            );
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('day-time heatmap buckets rows by site-local weekday and hour', function () use (
        $assertTimezoneAxis,
        $redirectFixture
    ) {
        $assertTimezoneAxis();
        $table = FsmtpFactory::emailLogTable(false, true);
        $today = current_datetime()->setTime(0, 0, 0);
        $previous = $today->modify('-1 day');
        FsmtpFactory::insertLog($table, ['created_at' => $previous->setTime(23, 30)->format('Y-m-d H:i:s')]);
        FsmtpFactory::insertLog($table, ['created_at' => $today->setTime(0, 15)->format('Y-m-d H:i:s')]);
        FsmtpFactory::insertLog($table, ['created_at' => $today->setTime(0, 45)->format('Y-m-d H:i:s')]);

        try {
            $result = $redirectFixture($table, function () {
                return FsmtpTest::ajax('GET', '/day-time-stats', ['last_day' => 0]);
            });
            $strictFailure = strpos($result['db_error'], 'ORDER BY clause is not in GROUP BY clause') !== false
                && strpos($result['db_error'], 'only_full_group_by') !== false;
            if (FsmtpTest::knownFailure(
                $strictFailure,
                'heatmap ordering is rejected by ONLY_FULL_GROUP_BY while exercising the timezone axis (app/Http/Controllers/DashboardController.php:79-81).'
            )) {
                return;
            }
            FsmtpTest::assertAjaxHealthy($result, 'site-local day-time heatmap');
            $stats = isset($result['data']['stats']) ? $result['data']['stats'] : [];

            FsmtpTest::assertSame(1, isset($stats[$previous->format('D')]['23:00']) ? $stats[$previous->format('D')]['23:00'] : null, 'previous-day heatmap bucket');
            FsmtpTest::assertSame(2, isset($stats[$today->format('D')]['0:00']) ? $stats[$today->format('D')]['0:00'] : null, 'today heatmap bucket');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('Logger aggregates return exact values under strict SQL modes', function () use ($withStrictFixture) {
        global $wpdb;
        $withStrictFixture(function ($table) use ($wpdb) {
            $logger = FsmtpFactory::loggerForTable($table);
            FsmtpTest::assertSame(['sent' => '3', 'failed' => '1'], $logger->getStats(), 'status aggregate');
            FsmtpTest::assertSame(2, $logger->getTotalCountStat('sent', '2025-01-01', '2025-01-31'), 'bounded status aggregate');
            FsmtpTest::assertSame(1, $logger->getTotalCountStat('failed', '2025-02-01'), 'open-ended status aggregate');
            FsmtpTest::assertSame(1, $logger->getSubjectCountStat('sent', '2025-01-01', '2025-01-31'), 'distinct-subject aggregate');
            $subjects = $logger->getSubjectStat('sent', '2025-01-01', '2025-12-31', 5);
            FsmtpTest::assertSame('alpha', isset($subjects[0]['subject']) ? $subjects[0]['subject'] : null, 'grouped subject leader');
            FsmtpTest::assertSame('2', isset($subjects[0]['emails_sent']) ? $subjects[0]['emails_sent'] : null, 'grouped subject count');
            FsmtpTest::assertSame('', (string)$wpdb->last_error, 'Logger strict aggregate database error');

            $activeModes = array_map('strtoupper', explode(',', (string)$wpdb->get_var('SELECT @@SESSION.sql_mode')));
            FsmtpTest::assert(in_array('ONLY_FULL_GROUP_BY', $activeModes, true), 'ONLY_FULL_GROUP_BY was not active');
            FsmtpTest::assert(in_array('STRICT_TRANS_TABLES', $activeModes, true), 'STRICT_TRANS_TABLES was not active');
        });
    });

    FsmtpTest::case('daily report aggregate returns values under strict SQL modes', function () use (
        $withStrictFixture,
        $redirectFixture
    ) {
        global $wpdb;
        $withStrictFixture(function ($table) use ($wpdb, $redirectFixture) {
            $stats = $redirectFixture($table, function () {
                return (new Reporting())->getSendingStats('2025-01-06', '2025-01-10');
            });

            FsmtpTest::assertSame('', (string)$wpdb->last_error, 'daily report strict database error');
            FsmtpTest::assertSame(['sent' => 1, 'failed' => 0], isset($stats['2025-01-06']) ? $stats['2025-01-06'] : null, 'strict daily report first bucket');
            FsmtpTest::assertSame(['sent' => 1, 'failed' => 0], isset($stats['2025-01-07']) ? $stats['2025-01-07'] : null, 'strict daily report second bucket');
        });
    });

    FsmtpTest::case('weekly report aggregate is tracked under strict SQL modes', function () use (
        $withStrictFixture,
        $redirectFixture
    ) {
        global $wpdb;
        $withStrictFixture(function ($table) use ($wpdb, $redirectFixture) {
            $wasSuppressing = $wpdb->suppress_errors();
            try {
                $stats = $redirectFixture($table, function () {
                    return (new Reporting())->getSendingStats('2025-01-01', '2025-04-30');
                });
                $error = (string)$wpdb->last_error;
            } finally {
                $wpdb->suppress_errors($wasSuppressing);
            }

            $known = strpos($error, 'not in GROUP BY clause') !== false
                && strpos($error, 'only_full_group_by') !== false;
            if (!FsmtpTest::knownFailure(
                $known,
                'Reporting weekly SELECT is rejected by ONLY_FULL_GROUP_BY (app/Services/Reporting.php:40,58).'
            )) {
                FsmtpTest::assertSame('', $error, 'weekly report strict database error');
                FsmtpTest::assertSame(['sent' => 2, 'failed' => 0], isset($stats['2025-01-06']) ? $stats['2025-01-06'] : null, 'strict weekly report bucket');
            }
        });
    });

    FsmtpTest::case('monthly report aggregate is tracked under strict SQL modes', function () use (
        $withStrictFixture,
        $redirectFixture
    ) {
        global $wpdb;
        $withStrictFixture(function ($table) use ($wpdb, $redirectFixture) {
            $wasSuppressing = $wpdb->suppress_errors();
            try {
                $stats = $redirectFixture($table, function () {
                    return (new Reporting())->getSendingStats('2025-01-01', '2025-12-31');
                });
                $error = (string)$wpdb->last_error;
            } finally {
                $wpdb->suppress_errors($wasSuppressing);
            }

            $known = strpos($error, 'not in GROUP BY clause') !== false
                && strpos($error, 'only_full_group_by') !== false;
            if (!FsmtpTest::knownFailure(
                $known,
                'Reporting monthly SELECT is rejected by ONLY_FULL_GROUP_BY (app/Services/Reporting.php:45,58).'
            )) {
                FsmtpTest::assertSame('', $error, 'monthly report strict database error');
                FsmtpTest::assertSame(['sent' => 2, 'failed' => 0], isset($stats['Jan 2025']) ? $stats['Jan 2025'] : null, 'strict monthly report bucket');
            }
        });
    });

    FsmtpTest::case('day-time heatmap aggregate is tracked under strict SQL modes', function () use (
        $withStrictFixture,
        $redirectFixture
    ) {
        global $wpdb;
        $withStrictFixture(function ($table) use ($wpdb, $redirectFixture) {
            $wasSuppressing = $wpdb->suppress_errors();
            try {
                $result = $redirectFixture($table, function () {
                    return FsmtpTest::ajax('GET', '/day-time-stats', ['last_day' => 0]);
                });
                $error = (string)$result['db_error'];
            } finally {
                $wpdb->suppress_errors($wasSuppressing);
            }

            $known = strpos($error, 'ORDER BY clause is not in GROUP BY clause') !== false
                && strpos($error, 'only_full_group_by') !== false;
            if (!FsmtpTest::knownFailure(
                $known,
                'heatmap FIELD(DAYNAME(created_at)) ordering is rejected by ONLY_FULL_GROUP_BY (app/Http/Controllers/DashboardController.php:60-62,79-81).'
            )) {
                FsmtpTest::assertAjaxHealthy($result, 'strict heatmap aggregate');
                $stats = isset($result['data']['stats']) ? $result['data']['stats'] : [];
                FsmtpTest::assertSame(1, isset($stats['Mon']['10:00']) ? $stats['Mon']['10:00'] : null, 'strict heatmap bucket');
            }
        });
    });
};
