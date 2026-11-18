<?php

return function () {
    /*
     * A log row deleted while the list is still on screen (by another admin,
     * the log purge, or Delete All) must not stop a Retry with a PHP error.
     * On PHP 8, Logger::find() assigned a property on null and threw an Error,
     * which the routes' catch (\Exception) did not catch, so a bulk retry
     * died on the missing row after resending the rows before it.
     */
    $missingId = function ($table) {
        $id = FsmtpFactory::insertLog($table, ['status' => 'failed']);
        global $wpdb;
        $wpdb->delete($table, ['id' => $id]);
        return $id;
    };

    $route = function ($table, $path, $params) {
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            return FsmtpTest::ajax('POST', $path, $params);
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
        }
    };

    $row = function ($status) {
        return [
            'to'          => serialize([['email' => 'to1@example.test']]),
            'from'        => 'Suite Sender <sender@example.test>',
            'body'        => 'body',
            'headers'     => serialize(['content-type' => 'text/plain']),
            'attachments' => serialize([]),
            'extra'       => serialize([]),
            'status'      => $status,
            'retries'     => 0,
        ];
    };

    FsmtpTest::case('Logger::find() returns null for a row that does not exist', function () use ($missingId) {
        $table = FsmtpFactory::emailLogTable();
        try {
            $id = $missingId($table);
            FsmtpTest::assertSame(null, FsmtpFactory::loggerForTable($table)->find($id), 'find() on a deleted row');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    $requests = [
        'Retry'                        => ['type' => 'retry'],
        'Resend to another address'    => ['type' => 'resend', 'recipients' => ['other@example.test']],
    ];
    foreach ($requests as $label => $params) {
        FsmtpTest::case('a ' . $label . ' of a deleted row answers 404 with a message and sends nothing', function () use ($missingId, $route, $params) {
            FsmtpTest::assertMailSimulationActive();
            $table = FsmtpFactory::emailLogTable();
            $sends = 0;
            $count = function () use (&$sends) {
                $sends++;
            };
            add_action('phpmailer_init', $count);
            try {
                $id = $missingId($table);
                $result = $route($table, '/logs/retry', array_merge(['id' => $id], $params));

                FsmtpTest::assertSame(404, $result['status'], 'HTTP status');
                FsmtpTest::assertSame(false, isset($result['data']['success']) ? $result['data']['success'] : null, 'success flag');
                FsmtpTest::assertSame('This email log no longer exists.', FsmtpTest::ajaxMessage($result), 'message');
                FsmtpTest::assertSame(0, $sends, 'emails sent');
            } finally {
                remove_action('phpmailer_init', $count);
                FsmtpFactory::dropTable($table);
            }
        });
    }

    FsmtpTest::case('a bulk retry skips a deleted row and still retries the rows after it', function () use ($missingId, $route, $row) {
        FsmtpTest::assertMailSimulationActive();
        global $wpdb;
        $table = FsmtpFactory::emailLogTable();
        $sends = 0;
        $count = function () use (&$sends) {
            $sends++;
        };
        add_action('phpmailer_init', $count);
        try {
            $first = FsmtpFactory::insertLog($table, $row('failed'));
            $gone = $missingId($table);
            $last = FsmtpFactory::insertLog($table, $row('failed'));

            $result = $route($table, '/logs/retry-bulk', ['log_ids' => [$first, $gone, $last]]);

            FsmtpTest::assertSame(200, $result['status'], 'HTTP status');
            FsmtpTest::assertSame(2, $sends, 'emails sent');
            FsmtpTest::assert(
                false !== strpos(FsmtpTest::ajaxMessage($result), '1 could not be prepared for sending'),
                'message names the skipped row: ' . FsmtpTest::ajaxMessage($result)
            );
            foreach (['first' => $first, 'last' => $last] as $label => $id) {
                $retries = $wpdb->get_var($wpdb->prepare("SELECT retries FROM `{$table}` WHERE id = %d", $id));
                FsmtpTest::assertSame('1', (string) $retries, $label . ' row retried once');
            }
        } finally {
            remove_action('phpmailer_init', $count);
            FsmtpFactory::dropTable($table);
        }
    });
};
