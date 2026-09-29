<?php

use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\Mailgun\Handler as MailgunHandler;
use FluentMail\App\Services\Mailer\Providers\Postmark\Handler as PostmarkHandler;
use FluentMail\App\Services\Mailer\Providers\Smtp2Go\Handler as Smtp2GoHandler;
use FluentMail\App\Services\Mailer\Providers\SparkPost\Handler as SparkPostHandler;

return function () {
    /*
     * A display name can hold a comma: `"Jewel, Shah" <jewel@example.test>` is
     * one mailbox. wp_mail() used to split it on the comma, leaving the name
     * `Shah"`, and Postmark then rejected the unbalanced quote in its To field.
     * These cases pin the fix, and that every input without a comma in a name
     * is handled byte-for-byte as before.
     */

    FsmtpTest::case('an address list without a quoted name splits exactly like explode', function () {
        $lists = [
            '',
            'a@example.test',
            'a@example.test,b@example.test',
            ' a@example.test , b@example.test ',
            'Jewel, Shah <a@example.test>',
            'Jane Doe <a@example.test>, b@example.test,',
            ',,',
            // A quote that does not open an entry is a literal, never a quoted name.
            '12" Pizza <a@example.test>, 6" Sub <b@example.test>',
            'Alice (6" tall) <a@example.test>, Bob (6" tall) <b@example.test>',
            // An unclosed quoted name.
            '"Jewel, Shah <a@example.test>, b@example.test',
        ];

        foreach ($lists as $list) {
            FsmtpTest::assertSame(explode(',', $list), BaseHandler::splitAddressList($list), 'split(' . $list . ')');
        }
    });

    FsmtpTest::case('a quoted display name keeps its comma when the list is split', function () {
        FsmtpTest::assertSame(
            ['"Jewel, Shah" <a@example.test>', ' b@example.test'],
            BaseHandler::splitAddressList('"Jewel, Shah" <a@example.test>, b@example.test'),
            'quoted comma'
        );
        FsmtpTest::assertSame(
            ['"Jewel, Shah" <a@example.test>', ' "Doe, Jane" <b@example.test>'],
            BaseHandler::splitAddressList('"Jewel, Shah" <a@example.test>, "Doe, Jane" <b@example.test>'),
            'two quoted names'
        );
        FsmtpTest::assertSame(
            ['"Say \"hi\", Bob" <a@example.test>'],
            BaseHandler::splitAddressList('"Say \"hi\", Bob" <a@example.test>'),
            'escaped quote inside a quoted name'
        );
    });

    FsmtpTest::case('only a quoted display name holding a comma loses its quotes', function () {
        FsmtpTest::assertSame('Jewel, Shah', BaseHandler::unquoteName('"Jewel, Shah" '), 'quoted comma');
        FsmtpTest::assertSame('Say "hi", Bob', BaseHandler::unquoteName('"Say \"hi\", Bob"'), 'escaped quote');

        $unchanged = [
            'Jane Doe ',
            '"Jewel Shah" ',
            '"support@example.test" ',
            '"A, B" "C"',
            '"\"ACME\""',
            'Shah" ',
            '"',
        ];
        foreach ($unchanged as $name) {
            FsmtpTest::assertSame($name, BaseHandler::unquoteName($name), 'unquoteName(' . $name . ')');
        }
    });

    FsmtpTest::case('a name without a comma is formatted exactly as before', function () {
        $names = [
            'Jane Doe', 'Dr. Jane Doe', "O'Brien", 'jane@example.test', 'Jöwel Shah', 'Acme Inc.', '  ',
            'Acme (Support)', 'Re: Help', 'Joe\'s "Best" Pizza', 'Back\\slash', '"Jewel, Shah"',
        ];

        foreach ($names as $name) {
            FsmtpTest::assertSame(
                $name . ' <a@example.test>',
                BaseHandler::formatAddress('a@example.test', $name),
                'formatAddress(' . $name . ')'
            );
        }

        FsmtpTest::assertSame('a@example.test', BaseHandler::formatAddress('a@example.test', ''), 'empty name');
    });

    FsmtpTest::case('a name with a comma is quoted and escaped', function () {
        FsmtpTest::assertSame('"Jewel, Shah" <a@example.test>', BaseHandler::formatAddress('a@example.test', 'Jewel, Shah'), 'comma');
        FsmtpTest::assertSame('"Say \"hi\", Bob" <a@example.test>', BaseHandler::formatAddress('a@example.test', 'Say "hi", Bob'), 'quote');
        FsmtpTest::assertSame('"Back\\\\slash, Co" <a@example.test>', BaseHandler::formatAddress('a@example.test', 'Back\\slash, Co'), 'backslash');
    });

    $sendAndCapture = function ($to, $headers = []) {
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $seen = null;
        $listener = function ($phpmailer) use (&$seen) {
            $seen = [
                'to'       => $phpmailer->getToAddresses(),
                'cc'       => $phpmailer->getCcAddresses(),
                'bcc'      => $phpmailer->getBccAddresses(),
                'reply-to' => array_values($phpmailer->getReplyToAddresses()),
            ];
        };

        add_action('phpmailer_init', $listener);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            wp_mail($to, 'display name suite', 'body', $headers);
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            remove_action('phpmailer_init', $listener);
        }

        FsmtpTest::assert($seen !== null, 'phpmailer_init did not fire');
        return $seen;
    };

    FsmtpTest::case('wp_mail keeps a quoted name with a comma as one recipient', function () use ($sendAndCapture) {
        FsmtpTest::assertMailSimulationActive();

        $seen = $sendAndCapture('"Jewel, Shah" <jewel@example.test>');
        FsmtpTest::assertSame([['jewel@example.test', 'Jewel, Shah']], $seen['to'], 'To');

        $seen = $sendAndCapture('"Jewel, Shah" <jewel@example.test>, other@example.test');
        FsmtpTest::assertSame(
            [['jewel@example.test', 'Jewel, Shah'], ['other@example.test', '']],
            $seen['to'],
            'To list'
        );

        $seen = $sendAndCapture('to@example.test', [
            'Cc: "Doe, Jane" <jane@example.test>, plain@example.test',
            'Bcc: "Roe, Rick" <rick@example.test>',
            'Reply-To: "Help, Desk" <help@example.test>',
        ]);
        FsmtpTest::assertSame([['jane@example.test', 'Doe, Jane'], ['plain@example.test', '']], $seen['cc'], 'Cc');
        FsmtpTest::assertSame([['rick@example.test', 'Roe, Rick']], $seen['bcc'], 'Bcc');
        FsmtpTest::assertSame([['help@example.test', 'Help, Desk']], $seen['reply-to'], 'Reply-To');
    });

    FsmtpTest::case('wp_mail handles every other address as before', function () use ($sendAndCapture) {
        FsmtpTest::assertMailSimulationActive();

        $seen = $sendAndCapture('a@example.test, Jane Doe <b@example.test>');
        FsmtpTest::assertSame([['a@example.test', ''], ['b@example.test', 'Jane Doe']], $seen['to'], 'plain list');

        // Unquoted, the comma still ends the mailbox, as in core wp_mail().
        $seen = $sendAndCapture('Jewel, Shah <jewel@example.test>');
        FsmtpTest::assertSame([['jewel@example.test', 'Shah']], $seen['to'], 'unquoted comma');

        $seen = $sendAndCapture(['Jewel, Shah <jewel@example.test>']);
        FsmtpTest::assertSame([['jewel@example.test', 'Jewel, Shah']], $seen['to'], 'array item');

        $seen = $sendAndCapture('"Jewel Shah" <jewel@example.test>');
        FsmtpTest::assertSame([['jewel@example.test', '"Jewel Shah"']], $seen['to'], 'quoted name without a comma');

        $seen = $sendAndCapture('12" Pizza <a@example.test>, 6" Sub <b@example.test>');
        FsmtpTest::assertSame(
            [['a@example.test', '12" Pizza'], ['b@example.test', '6" Sub']],
            $seen['to'],
            'stray quotes'
        );
    });

    /*
     * Send through a real provider handler with the HTTP call intercepted, and
     * return the request body the provider would have received plus the
     * `from` the log would have stored.
     */
    $providerRequest = function ($handler, $apiUrl, array $settings, $toName, $fromName) {
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->setFrom('sender@example.test', $fromName, false);
        $mailer->addAddress('to@example.test', $toName);
        $mailer->addCC('cc@example.test', $toName);
        $mailer->addCC('plain@example.test');
        $mailer->Subject = 'display name suite';
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
                'body'     => wp_json_encode(['MessageID' => 'suite', 'Message' => 'OK', 'id' => 'suite', 'data' => ['succeeded' => 1]]),
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
                    'sender_name'     => '',
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

        $attributes = new ReflectionProperty($handler, 'attributes');
        $attributes->setAccessible(true);
        $loggedFrom = $attributes->getValue($handler)['from'];

        return [$captured, $loggedFrom];
    };

    FsmtpTest::case('Postmark receives a comma name quoted in To, Cc and From', function () use ($providerRequest) {
        $send = function ($toName, $fromName) use ($providerRequest) {
            return $providerRequest(new PostmarkHandler(), 'https://api.postmarkapp.com/email', [], $toName, $fromName);
        };

        list($body, $loggedFrom) = $send('Jewel, Shah', 'Acme, Inc.');
        FsmtpTest::assertSame('"Jewel, Shah" <to@example.test>', $body['To'], 'To');
        FsmtpTest::assertSame('"Jewel, Shah" <cc@example.test>, plain@example.test', $body['Cc'], 'Cc');
        FsmtpTest::assertSame('"Acme, Inc." <sender@example.test>', $body['From'], 'From');
        FsmtpTest::assertSame('Acme, Inc. <sender@example.test>', $loggedFrom, 'the logged from is unchanged');

        list($body, $loggedFrom) = $send('Jewel Shah', 'Joe\'s "Best" Pizza');
        FsmtpTest::assertSame('Jewel Shah <to@example.test>', $body['To'], 'plain To unchanged');
        FsmtpTest::assertSame('Jewel Shah <cc@example.test>, plain@example.test', $body['Cc'], 'plain Cc unchanged');
        FsmtpTest::assertSame('Joe\'s "Best" Pizza <sender@example.test>', $body['From'], 'From without a comma unchanged');
        FsmtpTest::assertSame($loggedFrom, $body['From'], 'From without a comma is the logged from');
    });

    FsmtpTest::case('Mailgun, SMTP2GO and SparkPost receive a comma name quoted', function () use ($providerRequest) {
        list($body) = $providerRequest(
            new MailgunHandler(),
            'https://api.mailgun.net/v3/',
            ['domain_name' => 'mg.example.test', 'region' => 'us'],
            'Jewel, Shah',
            'Acme, Inc.'
        );
        FsmtpTest::assertSame('"Jewel, Shah" <to@example.test>', $body['to'], 'Mailgun to');
        FsmtpTest::assertSame('"Acme, Inc." <sender@example.test>', $body['from'], 'Mailgun from');

        list($body) = $providerRequest(new Smtp2GoHandler(), 'https://api.smtp2go.com/', [], 'Jewel, Shah', 'Acme, Inc.');
        FsmtpTest::assertSame(['"Jewel, Shah" <to@example.test>'], $body['to'], 'SMTP2GO to');
        FsmtpTest::assertSame('"Acme, Inc." <sender@example.test>', $body['sender'], 'SMTP2GO sender');

        list($body) = $providerRequest(new SparkPostHandler(), 'https://api.sparkpost.com/', [], 'Jewel, Shah', 'Acme, Inc.');
        FsmtpTest::assertSame('"Acme, Inc." <sender@example.test>', $body['content']['from'], 'SparkPost from');

        list($body) = $providerRequest(
            new MailgunHandler(),
            'https://api.mailgun.net/v3/',
            ['domain_name' => 'mg.example.test', 'region' => 'us'],
            'Jewel Shah',
            'Acme (Support)'
        );
        FsmtpTest::assertSame('Jewel Shah <to@example.test>', $body['to'], 'Mailgun to without a comma unchanged');
        FsmtpTest::assertSame('Acme (Support) <sender@example.test>', $body['from'], 'Mailgun from without a comma unchanged');
    });
};
