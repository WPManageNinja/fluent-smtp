<?php

use FluentMail\App\Models\Logger;

return function () {
    FsmtpTest::case('log pruning uses fixed batches with query counts proportional to two fixture sizes', function () {
        global $wpdb;
        $batchSize = 3;
        $scenarios = [
            ['rows' => 2, 'queries' => 1],
            ['rows' => 8, 'queries' => 3],
        ];

        foreach ($scenarios as $scenario) {
            $table = FsmtpFactory::emailLogTable(false, true);
            $cutoff = current_datetime()->sub(new DateInterval('P14D'));
            for ($index = 0; $index < $scenario['rows']; $index++) {
                FsmtpFactory::insertLog($table, [
                    'created_at' => $cutoff->modify('-1 day -' . $index . ' minutes')->format('Y-m-d H:i:s'),
                ]);
            }

            $queries = [];
            $observer = function ($query) use (&$queries, $table) {
                if (stripos(ltrim($query), 'DELETE FROM') === 0 && strpos($query, $table) !== false) {
                    $queries[] = $query;
                }
                return $query;
            };
            $batchFilter = function () use ($batchSize) {
                return $batchSize;
            };

            add_filter('query', $observer, PHP_INT_MAX);
            add_filter('fluentmail_log_delete_batch_size', $batchFilter, PHP_INT_MAX);
            try {
                $deleted = FsmtpFactory::loggerForTable($table)->deleteLogsOlderThan(14);

                FsmtpTest::assertSame($scenario['rows'], $deleted, $scenario['rows'] . '-row deleted count');
                FsmtpTest::assertSame($scenario['queries'], count($queries), $scenario['rows'] . '-row DELETE query count');
                FsmtpTest::assertSame(0, (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"), $scenario['rows'] . '-row table remainder');
                foreach ($queries as $query) {
                    FsmtpTest::assert(
                        preg_match('/\sLIMIT\s+' . $batchSize . '\s*$/i', $query) === 1,
                        $scenario['rows'] . '-row DELETE did not keep the fixed batch size'
                    );
                }
            } finally {
                remove_filter('query', $observer, PHP_INT_MAX);
                remove_filter('fluentmail_log_delete_batch_size', $batchFilter, PHP_INT_MAX);
                FsmtpFactory::dropTable($table);
            }
        }
    });

    FsmtpTest::case('log pagination keeps a two-query budget and fixed page size at two table sizes', function () {
        $scenarios = [
            ['rows' => 4, 'page_rows' => 4],
            ['rows' => 40, 'page_rows' => 10],
        ];

        foreach ($scenarios as $scenario) {
            $table = FsmtpFactory::emailLogTable(false, true);
            for ($index = 0; $index < $scenario['rows']; $index++) {
                FsmtpFactory::insertLog($table, [
                    'subject' => 'throughput-' . $scenario['rows'] . '-' . $index,
                ]);
            }

            $redirect = FsmtpFactory::productionLogTableRedirect($table);
            $queries = [];
            $observer = function ($query) use (&$queries, $table) {
                if (stripos(ltrim($query), 'SELECT') === 0 && strpos($query, $table) !== false) {
                    $queries[] = $query;
                }
                return $query;
            };
            $oldGet = $_GET;
            $oldRequest = $_REQUEST;
            $_GET = ['page' => 1];
            $_REQUEST = ['per_page' => 10, 'page' => 1];

            add_filter('query', $redirect, PHP_INT_MAX - 1);
            add_filter('query', $observer, PHP_INT_MAX);
            try {
                $result = (new Logger())->get([
                    'page' => 1,
                    'per_page' => 10,
                    'status' => '',
                    'date_range' => [],
                    'search' => '',
                ]);

                FsmtpTest::assertSame(2, count($queries), $scenario['rows'] . '-row pagination query count');
                FsmtpTest::assertSame($scenario['rows'], $result['total'], $scenario['rows'] . '-row pagination total');
                FsmtpTest::assertSame($scenario['page_rows'], count($result['data']), $scenario['rows'] . '-row first-page batch size');
                FsmtpTest::assertSame(10, $result['per_page'], $scenario['rows'] . '-row pagination per-page value');
            } finally {
                remove_filter('query', $redirect, PHP_INT_MAX - 1);
                remove_filter('query', $observer, PHP_INT_MAX);
                $_GET = $oldGet;
                $_REQUEST = $oldRequest;
                FsmtpFactory::dropTable($table);
            }
        }
    });
};
