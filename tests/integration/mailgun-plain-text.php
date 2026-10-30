<?php

use FluentMail\App\Services\Html2Text;
use FluentMail\App\Services\Mailer\Providers\Mailgun\Handler as MailgunHandler;

return function () {
    /*
     * With "Plain Text Alternative" on, fluentMailSend() turns an HTML email
     * into multipart/alternative and fills AltBody from the HTML
     * (app/Functions/helpers.php, after phpmailer_init). These cases start from
     * that PHPMailer state and check the request the Mailgun handler builds:
     * it used to test for "html" in the content type first, so
     * multipart/alternative was sent as `text` holding the HTML source.
     */

    /*
     * Send through the real Mailgun handler with the HTTP call intercepted, and
     * return the request body Mailgun would have received. Same capture as
     * display-name-commas.php.
     */
    $mailgunRequest = function ($contentType, $body, $altBody = '') {
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->setFrom('sender@example.test', 'Sender', false);
        $mailer->addAddress('to@example.test');
        $mailer->Subject = 'mailgun plain text suite';
        $mailer->Body = $body;
        $mailer->AltBody = $altBody;
        $mailer->ContentType = $contentType;

        $captured = null;
        FsmtpTest::interceptHttp(function ($url, $args) use (&$captured) {
            if (strpos($url, 'https://api.mailgun.net/v3/') !== 0) {
                return null;
            }
            $captured = $args['body'];
            return [
                'headers'  => [],
                'body'     => wp_json_encode(['id' => 'suite', 'message' => 'Queued. Thank you.']),
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        });

        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            (new MailgunHandler())
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
            // Back to the suite's fail-closed default.
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assert(is_array($captured), 'the Mailgun request was not captured');

        return $captured;
    };

    $html = '<html><body><h1>Order received</h1><p>Thank you for your <strong>order</strong>.</p></body></html>';

    FsmtpTest::case('Mailgun sends multipart/alternative as html plus the plain text part', function () use ($mailgunRequest, $html) {
        FsmtpTest::assertMailSimulationActive();
        $altBody = (new Html2Text($html))->getText();
        FsmtpTest::assert('' !== $altBody && false === strpos($altBody, '<'), 'fixture AltBody is not plain text');

        $body = $mailgunRequest('multipart/alternative', $html, $altBody);

        FsmtpTest::assertSame($html, isset($body['html']) ? $body['html'] : null, 'Mailgun html');
        FsmtpTest::assertSame($altBody, isset($body['text']) ? $body['text'] : null, 'Mailgun text');
    });

    FsmtpTest::case('Mailgun sends text/html as html only', function () use ($mailgunRequest, $html) {
        FsmtpTest::assertMailSimulationActive();
        $body = $mailgunRequest('text/html', $html);

        FsmtpTest::assertSame($html, isset($body['html']) ? $body['html'] : null, 'Mailgun html');
        FsmtpTest::assert(!array_key_exists('text', $body), 'Mailgun sent a text part for text/html');
    });

    FsmtpTest::case('Mailgun sends text/plain as text only', function () use ($mailgunRequest) {
        FsmtpTest::assertMailSimulationActive();
        $plain = "Order received\n\nThank you for your order.";
        $body = $mailgunRequest('text/plain', $plain);

        FsmtpTest::assertSame($plain, isset($body['text']) ? $body['text'] : null, 'Mailgun text');
        FsmtpTest::assert(!array_key_exists('html', $body), 'Mailgun sent an html part for text/plain');
    });
};
