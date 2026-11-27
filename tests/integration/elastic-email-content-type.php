<?php

use FluentMail\App\Services\Mailer\Providers\ElasticMail\Handler as ElasticMailHandler;

return function () {
    /*
     * Elastic Email takes the body as bodyHtml and bodyText form fields. A content
     * type with parameters must be read as its bare type, as the other providers
     * read it: a wp_mail_content_type filter returning "text/html; charset=UTF-8"
     * (Bit Form 3.5.0 does) sent the HTML as bodyText, so recipients saw the tags.
     * wp_mail() hands the filter's value to PHPMailer unchanged and, since it is
     * not exactly "text/html", never calls isHTML(), so the cases set ContentType
     * the same way.
     */

    $send = function ($contentType, $altBody = '') {
        FsmtpTest::requirePhpMailer();
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom('sender@example.test', '', false);
        $mailer->addAddress('to@example.test');
        $mailer->Subject = 'elastic content type suite';
        $mailer->Body = '<p>html <b>body</b></p>';
        $mailer->AltBody = $altBody;
        $mailer->ContentType = $contentType;

        $requests = [];
        FsmtpTest::interceptHttp(function ($url, $args) use (&$requests) {
            if (strpos($url, 'api.elasticemail.com') === false) {
                return null;
            }
            $requests[] = $args;
            return [
                'headers'  => [],
                'body'     => '{"success":true,"data":{"transactionid":"suite","messageid":"suite"}}',
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        });

        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            (new ElasticMailHandler())
                ->setSettings([
                    'key_store'       => 'db',
                    'api_key'         => 'suite-key',
                    'mail_type'       => 'transactional',
                    'sender_email'    => 'sender@example.test',
                    'sender_name'     => '',
                    'force_from_name' => 'no',
                ])
                ->setPhpMailer($mailer)
                ->send();
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assertSame(1, count($requests), "one request reached Elastic Email ($contentType)");

        // The handler writes its own multipart body; empty fields are left out.
        $headers = array_change_key_case((array) $requests[0]['headers'], CASE_LOWER);
        FsmtpTest::assert((bool) preg_match('/boundary=(\S+)/', (string) ($headers['content-type'] ?? ''), $m), "request names its boundary ($contentType)");
        $fields = [];
        foreach (explode('--' . $m[1], (string) $requests[0]['body']) as $part) {
            if (preg_match('/name="?([^"\r\n;]+)"?\r\n\r\n(.*)\r\n$/s', $part, $p)) {
                $fields[$p[1]] = $p[2];
            }
        }

        return $fields;
    };

    FsmtpTest::case('Elastic Email: an HTML type with a charset sends the body as bodyHtml', function () use ($send) {
        foreach (['text/html; charset=UTF-8', 'text/html;charset=utf-8'] as $type) {
            $fields = $send($type);
            FsmtpTest::assertSame('<p>html <b>body</b></p>', $fields['bodyHtml'] ?? null, "bodyHtml ($type)");
            FsmtpTest::assertSame(false, isset($fields['bodyText']), "no bodyText ($type)");
        }
    });

    FsmtpTest::case('Elastic Email: a bare HTML type still sends bodyHtml only', function () use ($send) {
        $fields = $send('text/html');
        FsmtpTest::assertSame('<p>html <b>body</b></p>', $fields['bodyHtml'] ?? null, 'bodyHtml');
        FsmtpTest::assertSame(false, isset($fields['bodyText']), 'no bodyText');
    });

    FsmtpTest::case('Elastic Email: multipart/alternative sends the HTML as bodyHtml and AltBody as bodyText', function () use ($send) {
        $fields = $send('multipart/alternative', 'plain alternative');
        FsmtpTest::assertSame('<p>html <b>body</b></p>', $fields['bodyHtml'] ?? null, 'bodyHtml');
        FsmtpTest::assertSame('plain alternative', $fields['bodyText'] ?? null, 'bodyText');
    });

    FsmtpTest::case('Elastic Email: multipart/alternative with a charset and no AltBody, as a filter alone sets it, sends bodyHtml only', function () use ($send) {
        $fields = $send('multipart/alternative; charset=UTF-8');
        FsmtpTest::assertSame('<p>html <b>body</b></p>', $fields['bodyHtml'] ?? null, 'bodyHtml');
        FsmtpTest::assertSame(false, isset($fields['bodyText']), 'no bodyText');
    });

    FsmtpTest::case('Elastic Email: plain text goes as bodyText, with or without a charset', function () use ($send) {
        foreach (['text/plain', 'text/plain; charset=UTF-8'] as $type) {
            $fields = $send($type);
            FsmtpTest::assertSame('<p>html <b>body</b></p>', $fields['bodyText'] ?? null, "bodyText ($type)");
            FsmtpTest::assertSame(false, isset($fields['bodyHtml']), "no bodyHtml ($type)");
        }
    });
};
