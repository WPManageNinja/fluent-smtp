<?php

use FluentMail\App\Services\Mailer\Providers\SendGrid\Handler as SendGridHandler;

return function () {
    /*
     * SendGrid accounts on its EU data residency are refused on the global API
     * with 401 "User is not authorized to send mail based on their regional
     * attribute." A connection set to the EU region must post to the EU host;
     * every other connection, including all saved before the setting existed,
     * keeps posting to the global host.
     */

    $requestUrl = function (array $settings) {
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        }

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->setFrom('sender@example.test', '', false);
        $mailer->addAddress('to@example.test');
        $mailer->Subject = 'sendgrid region suite';
        $mailer->Body = 'body';
        $mailer->ContentType = 'text/plain';

        $urls = [];
        FsmtpTest::interceptHttp(function ($url) use (&$urls) {
            if (strpos($url, 'sendgrid.com') === false) {
                return null;
            }
            $urls[] = $url;
            return [
                'headers'  => [],
                'body'     => '',
                'response' => ['code' => 202, 'message' => 'Accepted'],
                'cookies'  => [],
                'filename' => null,
            ];
        });

        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            (new SendGridHandler())
                ->setSettings(array_merge([
                    'key_store'       => 'db',
                    'api_key'         => 'suite-key',
                    'sender_email'    => 'sender@example.test',
                    'sender_name'     => '',
                    'force_from_name' => 'no',
                ], $settings))
                ->setPhpMailer($mailer)
                ->send();
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assertSame(1, count($urls), 'one request reached SendGrid');

        return $urls[0];
    };

    FsmtpTest::case('a SendGrid connection set to the EU region posts to the EU host', function () use ($requestUrl) {
        FsmtpTest::assertSame('https://api.eu.sendgrid.com/v3/mail/send', $requestUrl(['region' => 'eu']), 'eu');
    });

    FsmtpTest::case('every other SendGrid connection posts to the global host', function () use ($requestUrl) {
        $global = 'https://api.sendgrid.com/v3/mail/send';
        FsmtpTest::assertSame($global, $requestUrl([]), 'saved before the region setting existed');
        FsmtpTest::assertSame($global, $requestUrl(['region' => 'global']), 'global');
        FsmtpTest::assertSame($global, $requestUrl(['region' => '']), 'empty');
        FsmtpTest::assertSame($global, $requestUrl(['region' => 'EU.evil.example.test']), 'unknown value');
        FsmtpTest::assertSame($global, $requestUrl(['region' => ['eu']]), 'non-string value');
    });

    FsmtpTest::case('a new SendGrid connection defaults to the global region', function () {
        $providers = fluentMail(\FluentMail\App\Services\Mailer\Manager::class)->getConfig('providers');
        FsmtpTest::assertSame('global', $providers['sendgrid']['options']['region'] ?? null, 'sendgrid default region');
    });
};
