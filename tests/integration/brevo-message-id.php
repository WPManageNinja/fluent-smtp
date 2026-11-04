<?php

use FluentMail\App\Services\Mailer\Providers\SendInBlue\Handler as SendInBlueHandler;

return function () {
    FsmtpTest::requirePhpMailer();

    $send = function ($messageId) {
        global $wpdb;

        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $apiUrl = 'https://api.brevo.com/v3/smtp/email';

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->setFrom('sender@example.test', 'Suite Sender', false);
        $mailer->addAddress('recipient@example.test');
        $mailer->Subject = 'Brevo message ID suite';
        $mailer->Body = 'body';
        $mailer->ContentType = 'text/plain';

        FsmtpTest::interceptHttp(function ($url) use ($apiUrl, $messageId) {
            if ($url !== $apiUrl) {
                return null;
            }

            return [
                'headers'  => [],
                'body'     => wp_json_encode(['messageId' => $messageId]),
                'response' => ['code' => 201, 'message' => 'Created'],
                'cookies'  => [],
                'filename' => null,
            ];
        });

        add_filter('query', $redirect, PHP_INT_MAX);
        add_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
        try {
            $result = (new SendInBlueHandler())
                ->setSettings([
                    'key_store'       => 'db',
                    'api_key'         => 'suite-key',
                    'sender_email'    => 'sender@example.test',
                    'sender_name'     => '',
                    'force_from_name' => 'no',
                ])
                ->setPhpMailer($mailer)
                ->send();
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            remove_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assertSame(true, $result, 'Brevo 201 response is successful');

        $logId = (int)$wpdb->get_var("SELECT id FROM `{$table}` ORDER BY id DESC LIMIT 1");
        FsmtpTest::assert($logId > 0, 'Brevo send created an isolated log row');

        // Read the isolated table directly: Logger::find() always queries the
        // production table, and the redirect filter is already removed here.
        $row = $wpdb->get_row($wpdb->prepare("SELECT response FROM `{$table}` WHERE id = %d", $logId), ARRAY_A);

        return ['response' => unserialize($row['response'], ['allowed_classes' => false])];
    };

    FsmtpTest::case('Brevo angle-bracketed message IDs survive email-log sanitization', function () use ($send) {
        $messageId = '<202609031856.15147286983@smtp-relay.mailin.fr>';
        $row = $send($messageId);

        FsmtpTest::assertSame(
            ['messageId' => '202609031856.15147286983@smtp-relay.mailin.fr'],
            $row['response'],
            'normalized Brevo response'
        );
    });

    FsmtpTest::case('Brevo message IDs without brackets remain unchanged', function () use ($send) {
        $messageId = '202609031856.15147286983@smtp-relay.mailin.fr';
        $row = $send($messageId);

        FsmtpTest::assertSame(
            ['messageId' => $messageId],
            $row['response'],
            'already-normalized Brevo response'
        );
    });
    FsmtpTest::case('the log sanitizer keeps bracketed message IDs from any provider', function () {
        $handler = new \FluentMail\App\Services\Mailer\BaseHandler();
        $sanitize = new ReflectionMethod($handler, 'recursiveSanitize');
        $sanitize->setAccessible(true);

        $result = $sanitize->invoke($handler, [
            'mailgun' => '<20111114174239.25659.5817@samples.mailgun.org>',
            'nested'  => ['id' => '<abc.123@relay.example.test>'],
            'short'   => '<x@a.b>',
            'no_dot'  => '<abc@localhost>',
            'mailto'  => '<mailto:unsub@example.test>',
            'newline' => "<a@b.test>\n",
        ]);

        FsmtpTest::assertSame('20111114174239.25659.5817@samples.mailgun.org', $result['mailgun'], 'Mailgun-style id');
        FsmtpTest::assertSame('abc.123@relay.example.test', $result['nested']['id'], 'nested id');
        // These are valid message IDs that sanitize_email() would mangle or erase.
        FsmtpTest::assertSame('x@a.b', $result['short'], 'short id is kept, not run through sanitize_email');
        FsmtpTest::assertSame('abc@localhost', $result['no_dot'], 'id without a dot in the domain');
        FsmtpTest::assertSame('mailto:unsub@example.test', $result['mailto'], 'bracketed mailto value');
        // A trailing newline means the value is not one bracketed token.
        FsmtpTest::assertSame(sanitize_text_field("<a@b.test>\n"), $result['newline'], 'trailing newline is not treated as an id');
    });

    FsmtpTest::case('the log sanitizer still treats other angle-bracket values as before', function () {
        $handler = new \FluentMail\App\Services\Mailer\BaseHandler();
        $sanitize = new ReflectionMethod($handler, 'recursiveSanitize');
        $sanitize->setAccessible(true);

        $inputs = [
            'name_address' => 'Help Desk <help@example.test>',
            'html'         => '<b>bold</b> text',
            'script'       => '<script>alert(1)</script>',
            'tag_like'     => '<img src=x onerror=alert(1)>',
            'two_ids'      => '<a@x.test><b@y.test>',
        ];
        $result = $sanitize->invoke($handler, $inputs);

        // None of these pass email or URL validation, so the old code sent each
        // one through sanitize_text_field(); that is the baseline to match.
        foreach ($inputs as $key => $value) {
            FsmtpTest::assertSame(sanitize_text_field($value), $result[$key], $key . ' unchanged from sanitize_text_field');
        }
    });
};
