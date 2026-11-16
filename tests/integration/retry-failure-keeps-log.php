<?php

return function () {
    /*
     * A Retry that fails again used to save the whole row back as read by
     * Logger::find(), whose `response` and `extra` are unserialized arrays, so
     * the row lost both. Only the retry count may change.
     */
    FsmtpTest::case('a failed retry keeps the row response and extra, and counts the retry', function () {
        FsmtpTest::assertMailSimulationActive();

        $response = ['code' => 500, 'message' => 'suite: original provider error'];
        $extra = ['provider' => 'Simulator', 'note' => 'suite: kept'];

        $table = FsmtpFactory::emailLogTable();
        $id = FsmtpFactory::insertLog($table, [
            'to'          => serialize([['email' => 'to1@example.test']]),
            'from'        => 'Suite Sender <sender@example.test>',
            'body'        => 'body',
            'headers'     => serialize(['content-type' => 'text/plain']),
            'attachments' => serialize([]),
            'status'      => 'failed',
            'response'    => serialize($response),
            'extra'       => serialize($extra),
            'retries'     => 2,
        ]);

        // setFrom() refuses this inside fluentMailSend()'s try, which fires wp_mail_failed.
        $badFrom = function () {
            return 'not-an-address';
        };
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        add_filter('wp_mail_from', $badFrom, PHP_INT_MAX);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            $result = FsmtpTest::ajax('POST', '/logs/retry', ['id' => $id, 'type' => 'retry']);
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            remove_filter('wp_mail_from', $badFrom, PHP_INT_MAX);
        }

        FsmtpTest::assertSame(false, isset($result['data']['success']) ? $result['data']['success'] : null, 'retry reported a failure');

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $id), ARRAY_A);

        FsmtpTest::assertSame($response, maybe_unserialize($row['response']), 'response kept');
        FsmtpTest::assertSame($extra, maybe_unserialize($row['extra']), 'extra kept');
        FsmtpTest::assertSame('3', (string) $row['retries'], 'retry counted');
        FsmtpTest::assertSame('failed', $row['status'], 'status unchanged');
    });
};
