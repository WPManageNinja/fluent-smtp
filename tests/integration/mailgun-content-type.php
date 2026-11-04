<?php

use FluentMail\App\Services\Mailer\Providers\Mailgun\Handler as MailgunHandler;

return function () {
    /*
     * Mailgun builds the MIME message itself from the `text` and `html` form
     * fields. The handler must put an HTML body in `html` (and AltBody in
     * `text` for multipart/alternative), must not hand Mailgun a Content-Type
     * header that overrides the one Mailgun writes, and must not change the
     * content type recorded for the email when it adds attachments.
     */

    $send = function ($contentType, $altBody = '', $attachment = null) {
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
        $mailer->Subject = 'mailgun content type suite';
        $mailer->Body = '<p>html body</p>';
        $mailer->AltBody = $altBody;
        $mailer->ContentType = $contentType;

        if ($attachment) {
            $mailer->addAttachment($attachment);
        }

        $requests = [];
        FsmtpTest::interceptHttp(function ($url, $args) use (&$requests) {
            if (strpos($url, 'mailgun.net') === false) {
                return null;
            }
            $requests[] = $args;
            return [
                'headers'  => [],
                'body'     => '{"id":"<suite@example.test>","message":"Queued. Thank you."}',
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        });

        $handler = new MailgunHandler();

        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            $handler
                ->setSettings([
                    'key_store'       => 'db',
                    'api_key'         => 'suite-key',
                    'domain_name'     => 'mg.example.test',
                    'region'          => 'us',
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

        FsmtpTest::assertSame(1, count($requests), 'one request reached Mailgun');

        $read = function () {
            return $this->attributes['headers']['content-type'];
        };

        return [
            'body'           => $requests[0]['body'],
            'headers'        => $requests[0]['headers'],
            'logged_type'    => \Closure::bind($read, $handler, MailgunHandler::class)(),
        ];
    };

    $formFields = function ($body, $contentTypeHeader) {
        if (is_array($body)) {
            return $body;
        }

        FsmtpTest::assertSame(
            true,
            (bool)preg_match('/boundary=(\S+)/', (string)$contentTypeHeader, $m),
            'multipart request names its boundary'
        );

        $fields = [];
        foreach (explode('--' . $m[1], $body) as $part) {
            if (preg_match('/name="([^"]+)"(?:; filename="[^"]*")?\r\n(?:[^\r\n]+\r\n)*\r\n(.*)\r\n$/s', $part, $p)) {
                $fields[$p[1]] = $p[2];
            }
        }

        return $fields;
    };

    FsmtpTest::case('plain-text mail goes to Mailgun as text only', function () use ($send) {
        $request = $send('text/plain');
        FsmtpTest::assertSame('<p>html body</p>', $request['body']['text'] ?? null, 'text field');
        FsmtpTest::assertSame(false, isset($request['body']['html']), 'no html field');
    });

    FsmtpTest::case('HTML mail goes to Mailgun as html only', function () use ($send) {
        foreach (['text/html', 'Text/HTML'] as $type) {
            $request = $send($type);
            FsmtpTest::assertSame('<p>html body</p>', $request['body']['html'] ?? null, "html field ($type)");
            FsmtpTest::assertSame(false, isset($request['body']['text']), "no text field ($type)");
        }
    });

    FsmtpTest::case('multipart/alternative mail sends the HTML body as html and AltBody as text', function () use ($send) {
        foreach (['multipart/alternative', 'multipart/alternative; boundary="b1"', 'Multipart/Alternative'] as $type) {
            $request = $send($type, 'plain alternative');
            FsmtpTest::assertSame('<p>html body</p>', $request['body']['html'] ?? null, "html field ($type)");
            FsmtpTest::assertSame('plain alternative', $request['body']['text'] ?? null, "text field ($type)");
        }
    });

    FsmtpTest::case('multipart/alternative mail without an AltBody sends html only', function () use ($send) {
        $request = $send('multipart/alternative');
        FsmtpTest::assertSame('<p>html body</p>', $request['body']['html'] ?? null, 'html field');
        FsmtpTest::assertSame(false, isset($request['body']['text']), 'no empty text field');
    });

    FsmtpTest::case('no Content-Type header is handed to Mailgun', function () use ($send) {
        foreach (['text/plain', 'text/html', 'multipart/alternative'] as $type) {
            $request = $send($type, 'plain alternative');
            FsmtpTest::assertSame(false, array_key_exists('h:Content-Type', $request['body']), "no h:Content-Type ($type)");
        }
    });

    FsmtpTest::case('HTML mail with an attachment keeps html, sends the file, and keeps its recorded content type', function () use ($send, $formFields) {
        $file = wp_tempnam('fsmtp-mailgun-suite');
        file_put_contents($file, 'attachment bytes');

        try {
            $request = $send('text/html', '', $file);
        } finally {
            @unlink($file);
        }

        $fields = $formFields($request['body'], $request['headers']['Content-Type'] ?? '');

        FsmtpTest::assertSame('<p>html body</p>', $fields['html'] ?? null, 'html field');
        FsmtpTest::assertSame('attachment bytes', $fields['attachment[0]'] ?? null, 'attachment part');
        FsmtpTest::assertSame(false, array_key_exists('h:Content-Type', $fields), 'no h:Content-Type');
        FsmtpTest::assertSame('text/html', $request['logged_type'], 'recorded content type');
    });
};
