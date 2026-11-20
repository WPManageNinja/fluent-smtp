<?php

use FluentMail\App\Services\Mailer\Providers\SendGrid\Handler;
use PHPMailer\PHPMailer\PHPMailer;

return function () {
    FsmtpTest::requirePhpMailer();

    $destination = '<mailto:leave@example.test>, <https://example.test/leave?a=1&b=2>';
    $instruction = 'List-Unsubscribe=One-Click';
    $pair = ['List-Unsubscribe' => $destination, 'List-Unsubscribe-Post' => $instruction];
    $cases = [
        'both headers' => [$pair + ['X-Fixture' => 'omit'], $pair],
        'destination only' => [
            ['List-Unsubscribe' => '<mailto:old@example.test>', 'list-unsubscribe' => $destination, 'LIST-UNSUBSCRIBE-POST' => ''],
            ['List-Unsubscribe' => $destination],
        ],
        'instruction only' => [['list-unsubscribe-post' => $instruction], ['List-Unsubscribe-Post' => $instruction]],
        'unencodable destination' => [
            ['List-Unsubscribe' => "<mailto:caf\xe9@example.test>", 'List-Unsubscribe-Post' => $instruction],
            ['List-Unsubscribe-Post' => $instruction],
        ],
        'unencodable instruction only' => [['List-Unsubscribe-Post' => "caf\xe9"], null],
        'unrelated headers' => [['X-Fixture' => 'omit'], null],
        'ordinary message' => [[], null],
    ];

    foreach ($cases as $label => $case) {
        FsmtpTest::case('SendGrid unsubscribe fields: ' . $label, function () use ($case) {
            list($input, $expected) = $case;
            FsmtpTest::assertMailSimulationActive();
            $mailer = new PHPMailer(true);
            $mailer->setFrom('sender@example.test');
            $mailer->addAddress('recipient@example.test');
            $mailer->Subject = 'Header fixture';
            $mailer->Body = 'Message';
            foreach ($input as $name => $value) {
                $mailer->addCustomHeader($name, $value);
            }

            $payload = null;
            FsmtpTest::interceptHttp(function ($url, $args) use (&$payload) {
                if ($url !== 'https://api.sendgrid.com/v3/mail/send') {
                    return null;
                }
                $payload = json_decode($args['body'], true);
                return ['response' => ['code' => 202, 'message' => 'Accepted'], 'headers' => [], 'body' => ''];
            });
            add_filter('fluentmail_will_log_email', '__return_false', PHP_INT_MAX);
            try {
                (new Handler())->setSettings([
                    'provider' => 'sendgrid', 'key_store' => 'db', 'api_key' => 'fixture',
                    'sender_email' => 'sender@example.test',
                ])->setPhpMailer($mailer)->send();
            } finally {
                remove_filter('fluentmail_will_log_email', '__return_false', PHP_INT_MAX);
                FsmtpTest::interceptHttp();
            }

            FsmtpTest::assert(is_array($payload), 'captured SendGrid JSON request');
            FsmtpTest::assertSame('Message', $payload['content'][0]['value'] ?? null, 'original message preserved');
            if (!is_array($payload)) {
                return;
            }
            FsmtpTest::assertSame($expected !== null, array_key_exists('headers', $payload), 'message headers present');
            FsmtpTest::assert($expected === ($payload['headers'] ?? null), 'message header values match the fixture');
        });
    }
};
