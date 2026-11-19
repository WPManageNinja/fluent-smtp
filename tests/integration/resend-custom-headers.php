<?php

return function () {
    /*
     * Logger::resendEmailFromLog() replays the custom headers stored in
     * extra.custom_headers. Each case resends a row from an isolated table and
     * reads the custom headers PHPMailer was given at phpmailer_init, after
     * wp_mail() has parsed them.
     */
    $stored = [
        ['key' => 'List-Unsubscribe', 'value' => '<https://example.test/?fluentcrm=1&route=unsubscribe&secure_hash=abc123>'],
        ['key' => 'List-Unsubscribe-Post', 'value' => 'List-Unsubscribe=One-Click'],
        ['key' => 'In-Reply-To', 'value' => '<p1@example.test>'],
        ['key' => 'References', 'value' => '<a1@example.test> <b2@example.test>'],
        ['key' => 'X-Suite-Utf8', 'value' => 'Zoë Bücher 例え'],
        ['key' => 'X-Empty', 'value' => ''],
    ];

    $resend = function ($extra, $type, array $recipients = []) {
        global $wpdb;
        $table = FsmtpFactory::emailLogTable();
        $id = FsmtpFactory::insertLog($table, [
            'to'          => serialize([['email' => 'to1@example.test']]),
            'from'        => 'Suite Sender <sender@example.test>',
            'body'        => 'body',
            'status'      => $type === 'resend' ? 'sent' : 'failed',
            'headers'     => serialize([
                'reply-to'     => [['email' => 'reply@example.test']],
                'cc'           => [],
                'bcc'          => [],
                'content-type' => 'text/plain',
            ]),
            'attachments' => serialize([]),
            'extra'       => is_array($extra) ? serialize($extra) : $extra,
        ]);

        $seen = null;
        $mailer = [];
        $listener = function ($phpmailer) use (&$seen, &$mailer) {
            $seen = array_map(function ($header) {
                return [$header[0], $header[1]];
            }, array_values($phpmailer->getCustomHeaders()));
            $mailer = [
                'from'         => $phpmailer->From,
                'reply-to'     => array_column($phpmailer->getReplyToAddresses(), 0),
                'cc'           => array_column($phpmailer->getCcAddresses(), 0),
                'bcc'          => array_column($phpmailer->getBccAddresses(), 0),
                'content-type' => $phpmailer->ContentType,
            ];
        };
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        add_action('phpmailer_init', $listener, PHP_INT_MAX);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            FsmtpFactory::loggerForTable($table)->resendEmailFromLog($id, $type, $recipients);
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            remove_action('phpmailer_init', $listener, PHP_INT_MAX);
        }

        FsmtpTest::assert($seen !== null, 'phpmailer_init did not fire during the resend');
        $row = $wpdb->get_row($wpdb->prepare("SELECT extra FROM `{$table}` WHERE id = %d", $id), ARRAY_A);

        return ['headers' => $seen, 'mailer' => $mailer, 'extra' => unserialize($row['extra'], ['allowed_classes' => false])];
    };

    $pairs = function (array $list) {
        return array_map(function ($header) {
            return [$header['key'], $header['value']];
        }, $list);
    };

    FsmtpTest::case('Retry, Resend to the original recipients and check_realtime replay every stored custom header', function () use ($resend, $stored, $pairs) {
        FsmtpTest::assertMailSimulationActive();

        foreach (['retry', 'resend', 'check_realtime'] as $type) {
            $result = $resend(['provider' => 'mailgun', 'custom_headers' => $stored], $type);
            FsmtpTest::assertSame($pairs($stored), $result['headers'], $type);
        }
    });

    FsmtpTest::case('stored From, Reply-To, Cc, Bcc, Content-Type, Message-ID and Date are not replayed', function () use ($resend) {
        $result = $resend(['custom_headers' => [
            ['key' => 'From', 'value' => 'Other Sender <other-sender@example.test>'],
            ['key' => 'Reply-To', 'value' => 'other@example.test'],
            ['key' => 'cc', 'value' => 'cc@example.test'],
            ['key' => 'Bcc', 'value' => 'bcc@example.test'],
            ['key' => 'Content-Type', 'value' => 'text/html'],
            ['key' => 'Message-ID', 'value' => '<original@example.test>'],
            ['key' => 'Date', 'value' => 'Mon, 02 Nov 2026 10:00:00 +0000'],
            ['key' => 'X-Kept', 'value' => 'yes'],
        ]], 'retry');

        FsmtpTest::assertSame([['X-Kept', 'yes']], $result['headers'], 'replayed custom headers');
        // The row's own values, as resendEmailFromLog() rebuilds them.
        // The row's From is added after the stored headers and wins, with or without 'from' in the skip list.
        FsmtpTest::assertSame('sender@example.test', $result['mailer']['from'], 'From');
        FsmtpTest::assertSame(['reply@example.test'], $result['mailer']['reply-to'], 'Reply-To addresses');
        FsmtpTest::assertSame([], $result['mailer']['cc'], 'Cc addresses');
        FsmtpTest::assertSame([], $result['mailer']['bcc'], 'Bcc addresses');
        FsmtpTest::assertSame('text/plain', $result['mailer']['content-type'], 'content type');
    });

    FsmtpTest::case('a stored repeat comes back once, because wp_mail() keys headers by name', function () use ($resend) {
        $result = $resend(['custom_headers' => [
            ['key' => 'X-Repeat', 'value' => 'first'],
            ['key' => 'X-Repeat', 'value' => 'second'],
        ]], 'retry');

        FsmtpTest::assertSame([['X-Repeat', 'second']], $result['headers'], 'replayed custom headers');
    });

    FsmtpTest::case('rows logged without custom headers resend with none', function () use ($resend) {
        foreach (['empty string' => '', 'empty array' => [], 'no key' => ['provider' => 'mailgun']] as $label => $extra) {
            $result = $resend($extra, 'retry');
            FsmtpTest::assertSame([], $result['headers'], $label);
        }
    });

    FsmtpTest::case('a resend to a different address carries no custom header', function () use ($resend, $stored) {
        $result = $resend(['custom_headers' => $stored], 'resend', ['other@example.test']);

        FsmtpTest::assertSame([], $result['headers'], 'custom headers on a resend to another address');
    });

    FsmtpTest::case('a successful Resend leaves the stored custom headers unchanged', function () use ($resend, $stored) {
        $result = $resend(['provider' => 'mailgun', 'custom_headers' => $stored], 'resend');

        FsmtpTest::assertSame($stored, $result['extra']['custom_headers'], 'stored custom headers after the resend');
        FsmtpTest::assertSame(1, count($result['extra']['resends']), 'the resend was recorded');
    });
};
