<?php

use FluentMail\App\Services\Mailer\Providers\Smtp2Go\Handler as Smtp2GoHandler;

return function () {
    $send = function (array $customHeaders) {
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        }

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->setFrom('sender@example.test', 'Sender', false);
        $mailer->addAddress('to@example.test');
        $mailer->addReplyTo('help@example.test');
        foreach ($customHeaders as $name => $value) {
            $mailer->addCustomHeader($name, $value);
        }
        $mailer->Subject = 'smtp2go custom headers suite';
        $mailer->Body = 'body';
        $mailer->ContentType = 'text/plain';

        $captured = null;
        FsmtpTest::interceptHttp(function ($url, $args) use (&$captured) {
            if (strpos($url, 'https://api.smtp2go.com/') !== 0) {
                return null;
            }
            $captured = is_string($args['body']) ? json_decode($args['body'], true) : $args['body'];
            return [
                'headers'  => [],
                'body'     => wp_json_encode(['data' => ['succeeded' => 1, 'email_id' => 'suite']]),
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        });

        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            (new Smtp2GoHandler())
                ->setSettings([
                    'key_store'       => 'db',
                    'api_key'         => 'suite-key',
                    'sender_email'    => 'sender@example.test',
                    'sender_name'     => 'Sender',
                    'force_from_name' => 'no',
                ])
                ->setPhpMailer($mailer)
                ->send();
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assert(is_array($captured), 'the SMTP2GO request was not captured');

        return isset($captured['custom_headers']) ? $captured['custom_headers'] : [];
    };

    FsmtpTest::case('SMTP2GO sends List-Unsubscribe and other custom headers', function () use ($send) {
        $headers = $send([
            'List-Unsubscribe'      => '<https://example.test/u/1>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);

        FsmtpTest::assertSame([
            ['header' => 'Reply-To', 'value' => 'help@example.test'],
            ['header' => 'List-Unsubscribe', 'value' => '<https://example.test/u/1>'],
            ['header' => 'List-Unsubscribe-Post', 'value' => 'List-Unsubscribe=One-Click'],
        ], $headers);
    });

    FsmtpTest::case('SMTP2GO sends Reply-To once when it is also set as a custom header', function () use ($send) {
        $headers = $send(['Reply-To' => 'other@example.test']);

        FsmtpTest::assertSame([
            ['header' => 'Reply-To', 'value' => 'help@example.test'],
        ], $headers);
    });
};
