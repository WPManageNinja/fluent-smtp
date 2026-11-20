<?php

use FluentMail\App\Services\Mailer\Providers\Smtp2Go\Handler;
use PHPMailer\PHPMailer\PHPMailer;

return function () {
    FsmtpTest::requirePhpMailer();

    $plain = "First paragraph\r\n\r\nDetails: https://example.test/info?a=1&b=2";
    $html = '<p>Details</p>';
    $cases = [
        // Content type, primary body, alternative, expected HTML, expected text, attachment.
        'plain text' => ['text/plain', $plain, '', '', $plain, false],
        'plain text with attachment' => ['Text/Plain; charset=UTF-8', $plain, 'unused alternative', '', $plain, true],
        'HTML' => ['text/html', $html, '', $html, '', false],
        'HTML with alternative' => ['text/html', $html, $plain, $html, $plain, false],
        'multipart' => ['multipart/alternative; boundary=b1', $html, $plain, $html, $plain, false],
        'other MIME type' => ['text/calendar', 'event', '', 'event', '', false],
    ];

    foreach ($cases as $label => $case) {
        FsmtpTest::case('SMTP2GO request body for ' . $label, function () use ($case) {
            list($type, $message, $alternative, $expectedHtml, $expectedText, $attach) = $case;
            FsmtpTest::assertMailSimulationActive();
            $mailer = new PHPMailer(true);
            $mailer->setFrom('sender@example.test');
            $mailer->addAddress('recipient@example.test');
            $mailer->Subject = 'SMTP2GO content suite';
            $mailer->ContentType = $type;
            $mailer->Body = $message;
            $mailer->AltBody = $alternative;
            if ($attach) {
                $mailer->addStringAttachment('fixture bytes', 'notes.txt');
            }

            $payload = null;
            FsmtpTest::interceptHttp(function ($url, $args) use (&$payload) {
                if ($url !== 'https://api.smtp2go.com/v3/email/send') {
                    return null;
                }
                $payload = json_decode($args['body'], true);
                return [
                    'headers' => [],
                    'body' => '{"data":{"succeeded":1}}',
                    'response' => ['code' => 200, 'message' => 'OK'],
                ];
            });
            add_filter('fluentmail_will_log_email', '__return_false', PHP_INT_MAX);
            try {
                (new Handler())->setSettings([
                    'provider' => 'smtp2go',
                    'key_store' => 'db',
                    'api_key' => 'fixture',
                    'sender_email' => 'sender@example.test',
                ])->setPhpMailer($mailer)->send();
            } finally {
                remove_filter('fluentmail_will_log_email', '__return_false', PHP_INT_MAX);
                FsmtpTest::interceptHttp();
            }

            FsmtpTest::assertSame($expectedText, $payload['text_body'] ?? null, 'SMTP2GO text_body');
            FsmtpTest::assertSame($expectedHtml, $payload['html_body'] ?? null, 'SMTP2GO html_body');
            if ($attach) {
                FsmtpTest::assertSame('notes.txt', $payload['attachments'][0]['filename'] ?? null, 'attachment name');
                FsmtpTest::assertSame(base64_encode('fixture bytes'), $payload['attachments'][0]['fileblob'] ?? null, 'attachment bytes');
            }
        });
    }
};
