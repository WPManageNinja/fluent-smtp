<?php

use FluentMail\App\Services\Mailer\Providers\Cloudflare\Handler as CloudflareHandler;
use FluentMail\App\Services\Mailer\Providers\Postmark\Handler as PostmarkHandler;
use FluentMail\App\Services\Mailer\Providers\Smtp2Go\Handler as Smtp2GoHandler;
use FluentMail\App\Services\Mailer\Providers\SparkPost\Handler as SparkPostHandler;

return function () {
    /*
     * `Reply-To: Help Desk <help@example.test>` reaches every handler as
     * ['email' => ..., 'name' => ...]. SMTP2GO, Postmark, SparkPost and
     * Cloudflare used to send only the address. These cases pin that the
     * name now reaches the provider in that provider's own address format,
     * and that a Reply-To without a name is sent exactly as before.
     * PepiPost is not covered: its v5 API documents reply_to as an
     * address-only string, so it keeps sending the bare address.
     */

    $providerRequest = function ($handler, $apiUrl, array $settings, $replyToName) {
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
        $mailer->addReplyTo('help@example.test', $replyToName);
        $mailer->Subject = 'reply-to name suite';
        $mailer->Body = 'body';
        $mailer->ContentType = 'text/plain';

        $captured = null;
        FsmtpTest::interceptHttp(function ($url, $args) use (&$captured, $apiUrl) {
            if (strpos($url, $apiUrl) !== 0) {
                return null;
            }
            $captured = is_string($args['body']) ? json_decode($args['body'], true) : $args['body'];
            return [
                'headers'  => [],
                'body'     => wp_json_encode(['MessageID' => 'suite', 'Message' => 'OK', 'id' => 'suite', 'success' => true, 'data' => ['succeeded' => 1]]),
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        });

        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            $handler
                ->setSettings(array_merge([
                    'key_store'       => 'db',
                    'api_key'         => 'suite-key',
                    'sender_email'    => 'sender@example.test',
                    'sender_name'     => 'Sender',
                    'force_from_name' => 'no',
                ], $settings))
                ->setPhpMailer($mailer)
                ->send();
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            // Back to the suite's fail-closed default.
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assert(is_array($captured), 'the request to ' . $apiUrl . ' was not captured');

        return $captured;
    };

    $smtp2GoReplyTo = function ($body) {
        $values = [];
        foreach ((array)(isset($body['custom_headers']) ? $body['custom_headers'] : []) as $header) {
            if (isset($header['header']) && strtolower($header['header']) === 'reply-to') {
                $values[] = $header['value'];
            }
        }
        return $values;
    };

    $send = [
        'SMTP2GO'    => function ($name) use ($providerRequest, $smtp2GoReplyTo) {
            return $smtp2GoReplyTo($providerRequest(new Smtp2GoHandler(), 'https://api.smtp2go.com/', [], $name));
        },
        'Postmark'   => function ($name) use ($providerRequest) {
            $body = $providerRequest(new PostmarkHandler(), 'https://api.postmarkapp.com/email', [], $name);
            return isset($body['ReplyTo']) ? $body['ReplyTo'] : null;
        },
        'SparkPost'  => function ($name) use ($providerRequest) {
            $body = $providerRequest(new SparkPostHandler(), 'https://api.sparkpost.com/', [], $name);
            return isset($body['content']['reply_to']) ? $body['content']['reply_to'] : null;
        },
        'Cloudflare' => function ($name) use ($providerRequest) {
            $body = $providerRequest(new CloudflareHandler(), 'https://api.cloudflare.com/', ['account_id' => 'suiteaccount'], $name);
            return isset($body['reply_to']) ? $body['reply_to'] : null;
        },
    ];

    FsmtpTest::case('a Reply-To without a name is sent as the bare address, as before', function () use ($send) {
        FsmtpTest::assertSame(['help@example.test'], $send['SMTP2GO'](''), 'SMTP2GO');
        FsmtpTest::assertSame('help@example.test', $send['Postmark'](''), 'Postmark');
        FsmtpTest::assertSame('help@example.test', $send['SparkPost'](''), 'SparkPost');
        FsmtpTest::assertSame('help@example.test', $send['Cloudflare'](''), 'Cloudflare');
    });

    FsmtpTest::case('a Reply-To name reaches SMTP2GO, Postmark, SparkPost and Cloudflare', function () use ($send) {
        FsmtpTest::assertSame(['Help Desk <help@example.test>'], $send['SMTP2GO']('Help Desk'), 'SMTP2GO');
        FsmtpTest::assertSame('Help Desk <help@example.test>', $send['Postmark']('Help Desk'), 'Postmark');
        FsmtpTest::assertSame('Help Desk <help@example.test>', $send['SparkPost']('Help Desk'), 'SparkPost');
        FsmtpTest::assertSame(
            ['address' => 'help@example.test', 'name' => 'Help Desk'],
            $send['Cloudflare']('Help Desk'),
            'Cloudflare'
        );
    });

    FsmtpTest::case('a Reply-To name with a comma is quoted for the string formats', function () use ($send) {
        FsmtpTest::assertSame(['"Help, Desk" <help@example.test>'], $send['SMTP2GO']('Help, Desk'), 'SMTP2GO');
        FsmtpTest::assertSame('"Help, Desk" <help@example.test>', $send['Postmark']('Help, Desk'), 'Postmark');
        FsmtpTest::assertSame('"Help, Desk" <help@example.test>', $send['SparkPost']('Help, Desk'), 'SparkPost');
        FsmtpTest::assertSame(
            ['address' => 'help@example.test', 'name' => 'Help, Desk'],
            $send['Cloudflare']('Help, Desk'),
            'Cloudflare'
        );
    });

    FsmtpTest::case('a Reply-To name with any other special is quoted for the string formats', function () use ($send) {
        // A bare `Acme; Support <help@…>` parses as a different mailbox, or as a group for `:`.
        $quoted = [
            'Acme; Support'       => '"Acme; Support" <help@example.test>',
            'Acme: Home Services' => '"Acme: Home Services" <help@example.test>',
            'support@acme'        => '"support@acme" <help@example.test>',
            'Acme (Support)'      => '"Acme (Support)" <help@example.test>',
            'Dr. Jane <Doe>'      => '"Dr. Jane <Doe>" <help@example.test>',
            'Joe\'s "Best" Pizza' => '"Joe\'s \"Best\" Pizza" <help@example.test>',
            'Back\\slash'         => '"Back\\\\slash" <help@example.test>',
        ];

        foreach ($quoted as $name => $expected) {
            FsmtpTest::assertSame([$expected], $send['SMTP2GO']($name), 'SMTP2GO ' . $name);
            FsmtpTest::assertSame($expected, $send['Postmark']($name), 'Postmark ' . $name);
            FsmtpTest::assertSame($expected, $send['SparkPost']($name), 'SparkPost ' . $name);
        }

        FsmtpTest::assertSame(
            ['address' => 'help@example.test', 'name' => 'Acme; Support'],
            $send['Cloudflare']('Acme; Support'),
            'Cloudflare keeps the raw name in its object'
        );
    });

    FsmtpTest::case('a Reply-To name that needs no quoting is sent as typed', function () use ($send) {
        $unchanged = [
            'Help Desk'     => 'Help Desk <help@example.test>',
            "O'Brien & Co"  => "O'Brien & Co <help@example.test>",
            'Jöwel Shah'    => 'Jöwel Shah <help@example.test>',
            '"Help Desk"'   => '"Help Desk" <help@example.test>',
            '"Help, Desk"'  => '"Help, Desk" <help@example.test>',
        ];

        foreach ($unchanged as $name => $expected) {
            FsmtpTest::assertSame([$expected], $send['SMTP2GO']($name), 'SMTP2GO ' . $name);
            FsmtpTest::assertSame($expected, $send['Postmark']($name), 'Postmark ' . $name);
            FsmtpTest::assertSame($expected, $send['SparkPost']($name), 'SparkPost ' . $name);
        }
    });
};
