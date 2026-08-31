<?php

use FluentMail\App\Http\Controllers\LoggerController;
use FluentMail\App\Models\Logger;
use FluentMail\App\Services\Reporting;

/** Force the parent reporting path to receive identifiers outside its whitelist. */
class FsmtpSuiteUnlistedReporting extends Reporting
{
    protected function getGroupAndOrder($frequency)
    {
        return ['created_at', 'created_at'];
    }
}

return function () {
    /** Read one production method without hard-coding repository paths. */
    $methodSource = function ($class, $method) {
        $reflection = new ReflectionMethod($class, $method);
        $lines = file($reflection->getFileName());
        if ($lines === false) {
            throw new RuntimeException('Could not read reflected production source.');
        }

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    };

    $compactSource = function ($source) {
        return preg_replace('/\s+/', '', $source);
    };

    /** Query an isolated fixture through the same real builder used by Logger. */
    $selectFixtureIds = function ($table, callable $scope) {
        global $wpdb;

        if (strpos($table, $wpdb->prefix) !== 0) {
            throw new RuntimeException('Fixture table does not use the active WordPress prefix.');
        }

        $query = fluentMailDb()->table(substr($table, strlen($wpdb->prefix)));
        $scope($query);

        return array_map('intval', wp_list_pluck($query->get(), 'id'));
    };

    FsmtpTest::case('log navigation treats search wildcard characters as literals', function () {
        $table = FsmtpFactory::emailLogTable();
        $needle = FsmtpTest::uniq('literal') . '%_marker';
        $literalId = FsmtpFactory::insertLog($table, [
            'subject' => 'subject ' . $needle,
        ]);
        FsmtpFactory::insertLog($table, [
            'subject' => 'subject ' . str_replace('%_', 'AB', $needle),
        ]);

        try {
            $result = FsmtpFactory::loggerForTable($table)->navigate([
                'query' => $needle,
                'id'    => 0,
                'dir'   => 'next',
            ]);

            FsmtpTest::assertSame($literalId, isset($result['log']['id']) ? $result['log']['id'] : null, 'literal wildcard search result');
            FsmtpTest::assertSame(false, $result['next'], 'literal wildcard search did not match wildcard lookalike');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    /** Capture the navigation SELECTs issued against one isolated fixture table. */
    $captureSelects = function ($table, callable $act) {
        $queries = [];
        $observer = function ($query) use (&$queries, $table) {
            if (stripos(ltrim($query), 'SELECT') === 0 && strpos($query, $table) !== false) {
                $queries[] = $query;
            }
            return $query;
        };

        add_filter('query', $observer, PHP_INT_MAX);
        try {
            $result = $act();
        } finally {
            remove_filter('query', $observer, PHP_INT_MAX);
        }

        return [$result, $queries];
    };

    FsmtpTest::case('log navigation drops a filter column outside its whitelist', function () use ($captureSelects) {
        global $wpdb;

        $table = FsmtpFactory::emailLogTable();
        $firstId = FsmtpFactory::insertLog($table, ['subject' => 'whitelist first']);
        $secondId = FsmtpFactory::insertLog($table, ['subject' => 'whitelist second']);

        // Closes the identifier backtick and appends an always-false predicate,
        // so a column reaching the query unfiltered changes the returned row.
        $payload = 'subject` = \'no-such-subject\' AND `subject';

        try {
            list($result, $queries) = $captureSelects($table, function () use ($table, $firstId, $payload) {
                return FsmtpFactory::loggerForTable($table)->navigate([
                    'filter_by'       => $payload,
                    'filter_by_value' => 'whitelist',
                    'id'              => $firstId,
                    'dir'             => 'next',
                ]);
            });

            FsmtpTest::assertSame('', (string) $wpdb->last_error, 'unlisted filter database error');
            FsmtpTest::assertSame(1, count($queries), 'navigation SELECT count');
            FsmtpTest::assert(
                strpos($queries[0], 'no-such-subject') === false,
                'unlisted filter column reached the navigation SELECT'
            );
            FsmtpTest::assertSame(
                $secondId,
                isset($result['log']['id']) ? $result['log']['id'] : null,
                'navigation result with an unlisted filter'
            );
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('log navigation still filters on a whitelisted column', function () use ($captureSelects) {
        global $wpdb;

        $table = FsmtpFactory::emailLogTable();
        $originId = FsmtpFactory::insertLog($table, ['status' => 'sent']);
        FsmtpFactory::insertLog($table, ['status' => 'sent']);
        $failedId = FsmtpFactory::insertLog($table, ['status' => 'failed']);

        try {
            list($result, $queries) = $captureSelects($table, function () use ($table, $originId) {
                return FsmtpFactory::loggerForTable($table)->navigate([
                    'filter_by'       => 'status',
                    'filter_by_value' => 'failed',
                    'id'              => $originId,
                    'dir'             => 'next',
                ]);
            });

            FsmtpTest::assertSame('', (string) $wpdb->last_error, 'whitelisted filter database error');
            FsmtpTest::assert(
                strpos($queries[0], '`status` = ') !== false,
                'whitelisted filter column did not reach the navigation SELECT'
            );
            FsmtpTest::assertSame(
                $failedId,
                isset($result['log']['id']) ? $result['log']['id'] : null,
                'navigation result with a whitelisted filter'
            );
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('log navigation survives array-shaped filter and search values', function () {
        global $wpdb;

        $table = FsmtpFactory::emailLogTable();
        $firstId = FsmtpFactory::insertLog($table, ['subject' => 'array shaped first']);
        $secondId = FsmtpFactory::insertLog($table, ['subject' => 'array shaped second']);

        try {
            // `subject` takes a single value, so an array must be discarded
            // rather than reaching the string operations in buildWhere().
            $result = FsmtpFactory::loggerForTable($table)->navigate([
                'filter_by'       => 'subject',
                'filter_by_value' => ['array shaped', 'array shaped'],
                'query'           => ['array shaped'],
                'id'              => $firstId,
                'dir'             => 'next',
            ]);

            FsmtpTest::assertSame('', (string) $wpdb->last_error, 'array-shaped filter database error');
            FsmtpTest::assertSame(
                $secondId,
                isset($result['log']['id']) ? $result['log']['id'] : null,
                'navigation result with array-shaped values'
            );
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('log navigation narrows an array-shaped cursor id before binding', function () use ($captureSelects) {
        global $wpdb;

        $table = FsmtpFactory::emailLogTable();
        $firstId = FsmtpFactory::insertLog($table, ['subject' => 'cursor first']);
        $secondId = FsmtpFactory::insertLog($table, ['subject' => 'cursor second']);

        // prepare() rejects an array argument through _doing_it_wrong(), which
        // prints a notice into the AJAX response body on a debug site.
        $notices = [];
        $noticeObserver = function ($function) use (&$notices) {
            $notices[] = $function;
        };
        add_action('doing_it_wrong_run', $noticeObserver);

        try {
            list($result, $queries) = $captureSelects($table, function () use ($table, $firstId) {
                return FsmtpFactory::loggerForTable($table)->navigate([
                    'id'  => [$firstId, $firstId],
                    'dir' => 'next',
                ]);
            });

            FsmtpTest::assertSame('', (string) $wpdb->last_error, 'array-shaped cursor database error');
            FsmtpTest::assertSame([], $notices, 'array-shaped cursor raised a _doing_it_wrong notice');
            FsmtpTest::assertSame(1, count($queries), 'array-shaped cursor navigation SELECT count');
            FsmtpTest::assert(
                strpos($queries[0], "`id` > '0'") !== false,
                'array-shaped cursor did not bind as an integer'
            );
            FsmtpTest::assertSame(
                $firstId,
                isset($result['log']['id']) ? $result['log']['id'] : null,
                'navigation result with an array-shaped cursor'
            );
            FsmtpTest::assertSame(true, $result['next'], 'array-shaped cursor next flag');
            FsmtpTest::assert($secondId > $firstId, 'fixture ordering');
        } finally {
            remove_action('doing_it_wrong_run', $noticeObserver);
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('day-time statistics clamp the requested lookback to 365 days', function () {
        global $wpdb;

        $table = $wpdb->prefix . 'fsmpt_email_logs';
        $queries = [];
        $observer = function ($query) use (&$queries, $table) {
            if (stripos(ltrim($query), 'SELECT') === 0 && strpos($query, $table) !== false && strpos($query, 'DAYNAME(created_at)') !== false) {
                $queries[] = $query;
            }
            return $query;
        };

        add_filter('query', $observer, PHP_INT_MAX);
        try {
            $result = FsmtpTest::ajax('GET', '/day-time-stats', ['last_day' => '999999']);
        } finally {
            remove_filter('query', $observer, PHP_INT_MAX);
        }

        FsmtpTest::assertSame(1, count($queries), 'day-time statistics SELECT count');
        $query = isset($queries[0]) ? $queries[0] : '';
        FsmtpTest::assert(strpos($query, 'INTERVAL 365 DAY') !== false, 'day-time lookback was not clamped to 365');
        FsmtpTest::assert(strpos($query, '999999') === false, 'unclamped day-time lookback reached the SELECT');

        $strictFailure = strpos($result['db_error'], 'ORDER BY clause is not in GROUP BY clause') !== false
            && strpos($result['db_error'], 'only_full_group_by') !== false;
        if (!FsmtpTest::knownFailure(
            $strictFailure,
            'heatmap ordering is rejected by ONLY_FULL_GROUP_BY after the lookback is clamped (app/Http/Controllers/DashboardController.php:60-62).'
        )) {
            FsmtpTest::assertAjaxHealthy($result, 'clamped day-time statistics');
        }
    });

    FsmtpTest::case('sending statistics replace unlisted grouping columns with the daily whitelist fallback', function () {
        global $wpdb;

        $table = $wpdb->prefix . FLUENT_MAIL_DB_PREFIX . 'email_logs';
        $queries = [];
        $observer = function ($query) use (&$queries, $table) {
            if (stripos(ltrim($query), 'SELECT') === 0 && strpos($query, $table) !== false && strpos($query, 'GROUP BY') !== false) {
                $queries[] = $query;
            }
            return $query;
        };

        add_filter('query', $observer, PHP_INT_MAX);
        try {
            (new FsmtpSuiteUnlistedReporting())->getSendingStats('-7 days', 'today');
        } finally {
            remove_filter('query', $observer, PHP_INT_MAX);
        }

        FsmtpTest::assertSame('', (string) $wpdb->last_error, 'whitelisted reporting database error');
        FsmtpTest::assertSame(1, count($queries), 'sending statistics SELECT count');
        $query = isset($queries[0]) ? $queries[0] : '';
        FsmtpTest::assert(strpos($query, 'GROUP BY `date`') !== false, 'unlisted reporting group did not fall back to date');
        FsmtpTest::assert(strpos($query, 'ORDER BY `date` ASC') !== false, 'unlisted reporting order did not fall back to date');
        FsmtpTest::assert(strpos($query, 'GROUP BY `created_at`') === false, 'unlisted reporting group reached the SELECT');
    });

    FsmtpTest::case('log retry lookup remains scoped to the requested ID', function () use ($methodSource, $compactSource, $selectFixtureIds) {
        $controllerSource = $compactSource($methodSource(LoggerController::class, 'retry'));
        $resendSource = $compactSource($methodSource(Logger::class, 'resendEmailFromLog'));
        $findSource = $compactSource($methodSource(Logger::class, 'find'));

        FsmtpTest::assert(
            strpos($controllerSource, '$logger->resendEmailFromLog($request->get(\'id\')') !== false,
            'retry controller no longer passes the requested ID to the resend lookup'
        );
        FsmtpTest::assert(strpos($resendSource, '$this->find($id)') !== false, 'retry path no longer delegates its ID to Logger::find');
        FsmtpTest::assert(
            strpos($findSource, '->where(\'id\',$id)') !== false,
            'retry lookup no longer applies an exact ID predicate'
        );

        $table = FsmtpFactory::emailLogTable();
        $targetId = FsmtpFactory::insertLog($table, ['subject' => 'retry scope target']);
        $neighborId = FsmtpFactory::insertLog($table, ['subject' => 'retry scope neighbor']);

        try {
            $ids = $selectFixtureIds($table, function ($query) use ($targetId) {
                $query->where('id', $targetId . ' scope-marker');
            });

            FsmtpTest::assertSame([$targetId], $ids, 'retry-shaped ID selection');
            FsmtpTest::assert(!in_array($neighborId, $ids, true), 'retry-shaped selection included a neighboring log');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('log delete selection remains scoped to integer-like requested IDs', function () use ($methodSource, $compactSource, $selectFixtureIds) {
        $controllerSource = $compactSource($methodSource(LoggerController::class, 'delete'));
        $deleteSource = $compactSource($methodSource(Logger::class, 'delete'));

        FsmtpTest::assert(
            strpos($controllerSource, '$id=(array)$request->get(\'id\')') !== false,
            'delete controller no longer normalizes the requested IDs to an array'
        );
        FsmtpTest::assert(
            strpos($deleteSource, '$ids=array_filter($id,\'intval\')') !== false,
            'delete path no longer rejects IDs without an integer value'
        );
        FsmtpTest::assert(
            strpos($deleteSource, '->whereIn(\'id\',$ids)') !== false,
            'delete path no longer applies an ID-set predicate'
        );

        $table = FsmtpFactory::emailLogTable();
        $targetId = FsmtpFactory::insertLog($table, ['subject' => 'delete scope target']);
        $neighborId = FsmtpFactory::insertLog($table, ['subject' => 'delete scope neighbor']);
        $requested = [$targetId . ' scope-marker', 'not-a-log-id', '0'];
        $ids = array_filter($requested, 'intval');

        try {
            $selected = $selectFixtureIds($table, function ($query) use ($ids) {
                $query->whereIn('id', $ids);
            });

            FsmtpTest::assertSame([$targetId], $selected, 'delete-shaped ID selection');
            FsmtpTest::assert(!in_array($neighborId, $selected, true), 'delete-shaped selection included a neighboring log');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });
};
