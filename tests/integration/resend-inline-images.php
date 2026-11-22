<?php

use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\ElasticMail\Handler as ElasticHandler;
use FluentMail\App\Services\Mailer\Providers\SendInBlue\Handler as BrevoHandler;

/*
 * A resend rebuilds the email from its log row, which holds the original HTML and
 * the real names. The email it rebuilds has to reach Brevo and Elastic Email in
 * the same shape as the first send. This file sorts after resend-attachments.php:
 * a resend turns logging off for the rest of the process.
 */
return function () {
    FsmtpTest::requirePhpMailer();

    $dir = trailingslashit(wp_upload_dir()['basedir']) . FsmtpTest::uniq('fsmtp-resend-inline');
    wp_mkdir_p($dir);
    $path = $dir . '/logo.png';
    file_put_contents($path, "\x89PNG\r\n\x1a\n" . str_repeat("\x00\xff", 40));

    /* Resend a log row and hand the rebuilt PHPMailer object to a provider handler. */
    $resendThrough = function ($class, $url, $status, $response) use ($path) {
        $record = new \PHPMailer\PHPMailer\PHPMailer(true);
        $record->addEmbeddedImage($path, 'logo', 'logo.png');

        $table = FsmtpFactory::emailLogTable();
        $subject = FsmtpTest::uniq('resend-inline');
        $id = FsmtpFactory::insertLog($table, [
            'to'          => serialize([['email' => 'to@example.test']]),
            'from'        => 'Suite Sender <sender@example.test>',
            'subject'     => $subject,
            'body'        => '<img src="cid:logo">',
            'headers'     => serialize(['content-type' => 'text/html']),
            'attachments' => serialize(BaseHandler::attachmentsForLog($record->getAttachments())),
            'extra'       => serialize([]),
        ]);

        $rebuilt = null;
        $listener = function ($phpmailer) use (&$rebuilt, $subject) {
            if ($phpmailer->Subject === $subject) {
                $rebuilt = clone $phpmailer;
            }
        };
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        $request = null;

        add_action('phpmailer_init', $listener, PHP_INT_MAX);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            try {
                FsmtpFactory::loggerForTable($table)->resendEmailFromLog($id, 'resend');
            } finally {
                remove_action('phpmailer_init', $listener, PHP_INT_MAX);
            }

            FsmtpTest::assert($rebuilt !== null, 'phpmailer_init fired during the resend');

            FsmtpTest::interceptHttp(function ($requested, $args) use (&$request, $url, $status, $response) {
                if (strpos($requested, $url) !== 0) {
                    return null;
                }
                $request = $args;
                return ['headers' => [], 'body' => $response, 'response' => ['code' => $status, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
            });

            (new $class())
                ->setSettings(['key_store' => 'db', 'api_key' => 'suite-key', 'sender_email' => 'sender@example.test', 'sender_name' => '', 'force_from_name' => 'no', 'mail_type' => 'transactional'])
                ->setPhpMailer($rebuilt)
                ->send();
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assert(is_array($request), 'a request reached ' . $url);

        return $request['body'];
    };

    FsmtpTest::case('resend: Brevo gets the same edited HTML and generated name as a first send', function () use ($resendThrough) {
        $body = json_decode($resendThrough(BrevoHandler::class, 'https://api.brevo.com/', 201, '{"messageId":"<suite@example.test>"}'), true);

        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $body['htmlContent'], 'html');
        FsmtpTest::assertSame(['inline-1.png'], array_column($body['attachment'], 'name'), 'names');
    });

    FsmtpTest::case('resend: Elastic Email gets the image named by its cid and the original HTML', function () use ($resendThrough) {
        $body = (string) $resendThrough(ElasticHandler::class, 'https://api.elasticemail.com/', 200, '{"success":true,"data":{"messageid":"suite"}}');

        FsmtpTest::assert(strpos($body, 'filename="logo"') !== false, 'named by cid');
        FsmtpTest::assert(strpos($body, '<img src="cid:logo">') !== false, 'html unchanged');
    });

    unlink($path);
    rmdir($dir);
};
