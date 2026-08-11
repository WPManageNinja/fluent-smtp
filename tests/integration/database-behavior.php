<?php

use FluentMailMigrations\EmailLogs;

require_once FLUENTMAIL_PLUGIN_PATH . 'database/migrations/EmailLogs.php';

return function () {
    FsmtpTest::case('2.3.0 log schema converges without losing status index coverage', function () {
        global $wpdb;
        $table = FsmtpFactory::emailLogTable(true, false);
        $observations = [];

        $observer = null;
        $observer = function ($query) use (&$observer, &$observations, $table) {
            if (stripos($query, 'ALTER TABLE') === false || strpos($query, $table) === false) {
                return $query;
            }

            remove_filter('query', $observer, PHP_INT_MAX);
            $indexes = FsmtpFactory::indexes($table);
            add_filter('query', $observer, PHP_INT_MAX);

            $hasStatusCoverage = false;
            foreach ($indexes as $columns) {
                if (in_array('status', $columns, true)) {
                    $hasStatusCoverage = true;
                    break;
                }
            }
            $observations[] = $hasStatusCoverage;
            return $query;
        };

        add_filter('query', $observer, PHP_INT_MAX);
        try {
            $method = new ReflectionMethod(EmailLogs::class, 'maybeUpgradeIndexes');
            $method->setAccessible(true);
            $method->invoke(null, $table);
        } finally {
            remove_filter('query', $observer, PHP_INT_MAX);
        }

        try {
            $indexes = FsmtpFactory::indexes($table);
            FsmtpTest::assertSame(
                ['created_at', 'status'],
                isset($indexes['created_at_status']) ? $indexes['created_at_status'] : null,
                'replacement index columns'
            );
            FsmtpTest::assert(!isset($indexes['status']), 'legacy status index was not removed');
            FsmtpTest::assertSame([true, true], $observations, 'status remained indexed before every DDL step');
            FsmtpTest::assertSame('', (string) $wpdb->last_error, 'migration database error');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('log index upgrade is idempotent after convergence', function () {
        global $wpdb;
        $table = FsmtpFactory::emailLogTable(false, true);
        $alters = 0;
        $observer = function ($query) use (&$alters, $table) {
            if (stripos($query, 'ALTER TABLE') !== false && strpos($query, $table) !== false) {
                $alters++;
            }
            return $query;
        };

        add_filter('query', $observer, PHP_INT_MAX);
        try {
            $method = new ReflectionMethod(EmailLogs::class, 'maybeUpgradeIndexes');
            $method->setAccessible(true);
            $method->invoke(null, $table);
            $method->invoke(null, $table);

            FsmtpTest::assertSame(0, $alters, 'converged schema emitted no ALTER TABLE statements');
            FsmtpTest::assertSame('', (string) $wpdb->last_error, 'idempotent migration database error');
        } finally {
            remove_filter('query', $observer, PHP_INT_MAX);
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('failed replacement index creation preserves the legacy status index', function () {
        global $wpdb;
        $table = FsmtpFactory::emailLogTable(true, false);
        $addAttempts = 0;
        $dropAttempts = 0;
        $ddlFailure = function ($query) use ($table, &$addAttempts, &$dropAttempts) {
            if (stripos($query, 'ALTER TABLE') === false || strpos($query, $table) === false) {
                return $query;
            }

            if (stripos($query, 'ADD INDEX `created_at_status`') !== false) {
                $addAttempts++;
                return "ALTER TABLE `{$table}` ADD INDEX `created_at_status` (`fsmtp_missing_column`)";
            }

            if (stripos($query, 'DROP INDEX `status`') !== false) {
                $dropAttempts++;
            }
            return $query;
        };

        $wasSuppressing = $wpdb->suppress_errors();
        add_filter('query', $ddlFailure, PHP_INT_MAX);
        try {
            $method = new ReflectionMethod(EmailLogs::class, 'maybeUpgradeIndexes');
            $method->setAccessible(true);
            $method->invoke(null, $table);
            $indexes = FsmtpFactory::indexes($table);

            FsmtpTest::assertSame(1, $addAttempts, 'replacement index creation attempt count');
            FsmtpTest::assertSame(0, $dropAttempts, 'legacy index drop attempt count after replacement failure');
            FsmtpTest::assertSame(['status'], isset($indexes['status']) ? $indexes['status'] : null, 'legacy status index after replacement failure');
            FsmtpTest::assert(!isset($indexes['created_at_status']), 'failed replacement index unexpectedly exists');
        } finally {
            remove_filter('query', $ddlFailure, PHP_INT_MAX);
            $wpdb->suppress_errors($wasSuppressing);
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('log pruning deletes only rows older than the cutoff in bounded batches', function () {
        global $wpdb;
        $table = FsmtpFactory::emailLogTable(false, true);
        $days = 14;
        $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - $days * DAY_IN_SECONDS);

        for ($index = 1; $index <= 5; $index++) {
            FsmtpFactory::insertLog($table, [
                'subject' => 'old-' . $index,
                'created_at' => gmdate('Y-m-d H:i:s', strtotime($cutoff . ' -2 hours -' . $index . ' minutes')),
            ]);
        }
        // Keep fixtures comfortably away from a wall-clock second boundary so
        // the test specifies cutoff behavior without becoming timing-sensitive.
        FsmtpFactory::insertLog($table, ['subject' => 'newer-1', 'created_at' => gmdate('Y-m-d H:i:s', strtotime($cutoff . ' +2 hours'))]);
        FsmtpFactory::insertLog($table, ['subject' => 'newer-2', 'created_at' => gmdate('Y-m-d H:i:s', strtotime($cutoff . ' +2 hours +1 minute'))]);
        FsmtpFactory::insertLog($table, ['subject' => 'newer-3', 'created_at' => gmdate('Y-m-d H:i:s', strtotime($cutoff . ' +2 hours +2 minutes'))]);

        $deleteQueries = 0;
        $queryFuse = function ($query) use (&$deleteQueries, $table) {
            if (stripos($query, 'DELETE FROM') !== false && strpos($query, $table) !== false) {
                $deleteQueries++;
                if ($deleteQueries > 10) {
                    throw new RuntimeException('delete loop exceeded the 10-query safety fuse');
                }
            }
            return $query;
        };
        $batchFilter = function () {
            return 2;
        };

        add_filter('query', $queryFuse, PHP_INT_MAX);
        add_filter('fluentmail_log_delete_batch_size', $batchFilter, PHP_INT_MAX);
        try {
            $deleted = FsmtpFactory::loggerForTable($table)->deleteLogsOlderThan($days);
            $remainingOld = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE created_at < %s", $cutoff)
            );
            $remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

            FsmtpTest::assertSame(5, $deleted, 'deleted old row count');
            FsmtpTest::assertSame(0, $remainingOld, 'no old fixtures remain');
            FsmtpTest::assertSame(3, $remaining, 'newer fixtures remain');
            FsmtpTest::assertSame(3, $deleteQueries, 'five rows were deleted in 2/2/1 batches');
        } finally {
            remove_filter('query', $queryFuse, PHP_INT_MAX);
            remove_filter('fluentmail_log_delete_batch_size', $batchFilter, PHP_INT_MAX);
            FsmtpFactory::dropTable($table);
        }
    });

    FsmtpTest::case('log pruning stops after one query when no row matches', function () {
        $table = FsmtpFactory::emailLogTable(false, true);
        FsmtpFactory::insertLog($table, ['created_at' => current_time('mysql', true)]);
        $deleteQueries = 0;
        $queryFuse = function ($query) use (&$deleteQueries, $table) {
            if (stripos($query, 'DELETE FROM') !== false && strpos($query, $table) !== false) {
                $deleteQueries++;
                if ($deleteQueries > 10) {
                    throw new RuntimeException('delete loop exceeded the 10-query safety fuse');
                }
            }
            return $query;
        };

        add_filter('query', $queryFuse, PHP_INT_MAX);
        try {
            $deleted = FsmtpFactory::loggerForTable($table)->deleteLogsOlderThan(14);
            FsmtpTest::assertSame(0, $deleted, 'no matching row was deleted');
            FsmtpTest::assertSame(1, $deleteQueries, 'no-match pruning issued one DELETE then stopped');
        } finally {
            remove_filter('query', $queryFuse, PHP_INT_MAX);
            FsmtpFactory::dropTable($table);
        }
    });
};
