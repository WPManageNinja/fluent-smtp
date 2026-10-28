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

        return FsmtpFactory::loggerForTable($table)->find($logId);
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
};
