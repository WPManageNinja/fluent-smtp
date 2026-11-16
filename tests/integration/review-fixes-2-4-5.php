<?php

use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\Smtp\Handler as SmtpHandler;
use FluentMail\App\Services\SecretMasker;

/*
 * Fixes from the pre-release review of 2.4.5:
 * - embedded images go out inline on the API providers that support it;
 * - the SMTP connection security is read the way PHPMailer and SecretMasker agree on;
 * - attachment path checks do not warn under open_basedir;
 * - Brevo can name an unnamed in-memory attachment of more types, and logs a skip;
 * - the 2.4.1 instance methods getAttachmentName() / secureFileRead() still exist.
 */

if (!class_exists('FsmtpSuiteLegacyAttachmentProvider')) {
    // A third-party provider written against 2.4.1: overrides both methods as
    // protected instance methods. A static parent method would make this a fatal.
    class FsmtpSuiteLegacyAttachmentProvider extends \FluentMail\App\Services\Mailer\Providers\Simulator\Handler
    {
        protected function getAttachmentName($attachment)
        {
            return 'legacy-' . parent::getAttachmentName($attachment);
        }

        protected function secureFileRead($filePath)
        {
            return parent::secureFileRead($filePath);
        }

        public function nameFor($attachment)
        {
            return $this->getAttachmentName($attachment);
        }
    }
}

if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
}

if (!class_exists('FsmtpSuiteSecureCapture')) {
    // Records the connection security a send was made with, without a network call.
    class FsmtpSuiteSecureCapture extends \PHPMailer\PHPMailer\PHPMailer
    {
        public $sentWith = null;

        public function send()
        {
            $this->sentWith = $this->SMTPSecure;
            return true;
        }
    }

    class FsmtpSuiteSmtpResultHandler extends SmtpHandler
    {
        public function handleResponse($response)
        {
            return $response;
        }
    }
}

return function () {
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
    }

    $png = "\x89PNG\r\n\x1a\n" . str_repeat("\x00\x01suite", 8);
    $pdf = "%PDF-1.4 review suite";

    /*
     * Send one HTML email with one embedded image (cid "suite-logo") and one
     * normal attachment through a provider; return the captured request args.
     */
    $send = function ($class, $urlPrefix, $status, array $settings = [], ?callable $build = null) use ($png, $pdf) {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom('sender@example.test', 'Suite');
        $mailer->addAddress('to@example.test');
        $mailer->Subject = 'inline suite';
        $mailer->isHTML(true);
        $mailer->Body = '<p><img src="cid:suite-logo"></p>';
        if ($build) {
            $build($mailer);
        } else {
            $mailer->addStringEmbeddedImage($png, 'suite-logo', 'logo.png', 'base64', 'image/png');
            $mailer->addStringAttachment($pdf, 'invoice.pdf', 'base64', 'application/pdf');
        }

        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $request = null;

        FsmtpTest::interceptHttp(function ($url, $args) use (&$request, $urlPrefix, $status) {
            if (strpos($url, $urlPrefix) !== 0) {
                return null;
            }
            $request = $args;
            return [
                'headers'  => [],
                'body'     => wp_json_encode([
                    'MessageID' => 'suite', 'ErrorCode' => 0, 'Message' => 'OK', 'id' => '<suite@example.test>',
                    'message' => 'Queued', 'success' => true, 'messageId' => 'suite',
                    'data' => ['succeeded' => 1, 'failed' => 0],
                    'results' => ['total_accepted_recipients' => 1, 'id' => 'suite'],
                    'result' => ['delivered' => ['to@example.test']],
                ]),
                'response' => ['code' => $status, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        });

        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            (new $class())
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

        FsmtpTest::assert(is_array($request), 'a request reached ' . $urlPrefix);

        return $request;
    };

    $json = function ($request) {
        return json_decode($request['body'], true);
    };

    FsmtpTest::case('inline images: Cloudflare and SendGrid send the image inline with its cid', function () use ($send, $json, $png) {
        foreach ([
            'Cloudflare' => [\FluentMail\App\Services\Mailer\Providers\Cloudflare\Handler::class, 'https://api.cloudflare.com/', 200, ['account_id' => 'suite-account']],
            'SendGrid'   => [\FluentMail\App\Services\Mailer\Providers\SendGrid\Handler::class, 'https://api.sendgrid.com/', 202, []],
        ] as $label => $spec) {
            $rows = $json($send($spec[0], $spec[1], $spec[2], $spec[3]))['attachments'];
            $byName = array_column($rows, null, 'filename');

            FsmtpTest::assertSame('inline', $byName['logo.png']['disposition'], $label . ' image disposition');
            FsmtpTest::assertSame('suite-logo', $byName['logo.png']['content_id'], $label . ' image cid');
            FsmtpTest::assertSame(base64_encode($png), $byName['logo.png']['content'], $label . ' image bytes');
            FsmtpTest::assertSame('attachment', $byName['invoice.pdf']['disposition'], $label . ' pdf disposition');
        }
    });

    FsmtpTest::case('inline images: Groundmail sets content_id on the image only, and sends in-memory files', function () use ($send, $json, $png, $pdf) {
        $rows = $json($send(\FluentMail\App\Services\Mailer\Providers\Groundmail\Handler::class, 'https://api.groundmail.dev/', 200))['attachments'];
        $byName = array_column($rows, null, 'name');

        FsmtpTest::assertSame(['logo.png', 'invoice.pdf'], array_column($rows, 'name'), 'both in-memory files sent');
        FsmtpTest::assertSame('suite-logo', $byName['logo.png']['content_id'], 'image cid');
        FsmtpTest::assertSame('image/png', $byName['logo.png']['content_type'], 'image type');
        FsmtpTest::assertSame(base64_encode($png), $byName['logo.png']['data'], 'image bytes');
        FsmtpTest::assert(!isset($byName['invoice.pdf']['content_id']), 'no content_id on the pdf');
        FsmtpTest::assertSame(base64_encode($pdf), $byName['invoice.pdf']['data'], 'pdf bytes');
    });

    FsmtpTest::case('inline images: Postmark sets ContentID with the cid: prefix on the image only', function () use ($send, $json) {
        $rows = $json($send(\FluentMail\App\Services\Mailer\Providers\Postmark\Handler::class, 'https://api.postmarkapp.com/', 200))['Attachments'];
        $byName = array_column($rows, null, 'Name');

        FsmtpTest::assertSame('cid:suite-logo', $byName['logo.png']['ContentID'], 'image ContentID');
        FsmtpTest::assert(!isset($byName['invoice.pdf']['ContentID']), 'no ContentID on the pdf');
    });

    FsmtpTest::case('inline images: SparkPost and SMTP2GO put the image in their inline list, named by cid', function () use ($send, $json, $png) {
        $spark = $json($send(\FluentMail\App\Services\Mailer\Providers\SparkPost\Handler::class, 'https://api.sparkpost.com/', 200))['content'];
        FsmtpTest::assertSame([['name' => 'suite-logo', 'type' => 'image/png', 'data' => base64_encode($png)]], $spark['inline_images'], 'SparkPost inline_images');
        FsmtpTest::assertSame(['invoice.pdf'], array_column($spark['attachments'], 'name'), 'SparkPost attachments');

        $smtp2go = $json($send(\FluentMail\App\Services\Mailer\Providers\Smtp2Go\Handler::class, 'https://api.smtp2go.com/', 200));
        FsmtpTest::assertSame([['mimetype' => 'image/png', 'filename' => 'suite-logo', 'fileblob' => base64_encode($png)]], $smtp2go['inlines'], 'SMTP2GO inlines');
        FsmtpTest::assertSame(['invoice.pdf'], array_column($smtp2go['attachments'], 'filename'), 'SMTP2GO attachments');
    });

    FsmtpTest::case('inline images: Mailgun sends the image as an inline part whose filename is the cid', function () use ($send, $png, $pdf) {
        $request = $send(\FluentMail\App\Services\Mailer\Providers\Mailgun\Handler::class, 'https://api.mailgun.net/', 200, ['domain_name' => 'mg.example.test', 'region' => 'us']);
        $body = (string) $request['body'];

        FsmtpTest::assert(strpos($body, 'name="inline"; filename="suite-logo"' . "\r\nContent-Type: image/png\r\n\r\n" . $png . "\r\n") !== false, 'inline part');
        FsmtpTest::assert(strpos($body, 'name="attachment[1]"; filename="invoice.pdf"' . "\r\nContent-Type: application/pdf\r\n\r\n" . $pdf . "\r\n") !== false, 'attachment part');
    });

    FsmtpTest::case('inline images: an email with only an inline image sends no empty attachment list', function () use ($send, $json, $png) {
        $onlyInline = function ($m) use ($png) {
            $m->addStringEmbeddedImage($png, 'only', 'only.png', 'base64', 'image/png');
        };

        $spark = $json($send(\FluentMail\App\Services\Mailer\Providers\SparkPost\Handler::class, 'https://api.sparkpost.com/', 200, [], $onlyInline))['content'];
        FsmtpTest::assert(!array_key_exists('attachments', $spark), 'SparkPost: no attachments key');
        FsmtpTest::assertSame(['only'], array_column($spark['inline_images'], 'name'), 'SparkPost inline_images');

        $smtp2go = $json($send(\FluentMail\App\Services\Mailer\Providers\Smtp2Go\Handler::class, 'https://api.smtp2go.com/', 200, [], $onlyInline));
        FsmtpTest::assert(!array_key_exists('attachments', $smtp2go), 'SMTP2GO: no attachments key');
        FsmtpTest::assertSame(['only'], array_column($smtp2go['inlines'], 'filename'), 'SMTP2GO inlines');
    });

    FsmtpTest::case('inline images: a repeated cid goes out once inline and then as a normal attachment', function () use ($send, $json, $png, $pdf) {
        // PHPMailer stores cid 0 for an inline attachment added without one.
        $twoZeros = function ($m) use ($png, $pdf) {
            $m->addStringEmbeddedImage($png, '0', 'logo.png', 'base64', 'image/png');
            $m->addStringAttachment($pdf, 'notes.pdf', 'base64', 'application/pdf', 'inline');
        };

        $spark = $json($send(\FluentMail\App\Services\Mailer\Providers\SparkPost\Handler::class, 'https://api.sparkpost.com/', 200, [], $twoZeros))['content'];
        FsmtpTest::assertSame(['0'], array_column($spark['inline_images'], 'name'), 'SparkPost: one inline image named 0');
        FsmtpTest::assertSame(['notes.pdf'], array_column($spark['attachments'], 'name'), 'SparkPost: the repeat is a normal attachment');

        $smtp2go = $json($send(\FluentMail\App\Services\Mailer\Providers\Smtp2Go\Handler::class, 'https://api.smtp2go.com/', 200, [], $twoZeros));
        FsmtpTest::assertSame(['0'], array_column($smtp2go['inlines'], 'filename'), 'SMTP2GO: one inline');
        FsmtpTest::assertSame(['notes.pdf'], array_column($smtp2go['attachments'], 'filename'), 'SMTP2GO: the repeat is a normal attachment');

        $mailgun = (string) $send(\FluentMail\App\Services\Mailer\Providers\Mailgun\Handler::class, 'https://api.mailgun.net/', 200, ['domain_name' => 'mg.example.test', 'region' => 'us'], $twoZeros)['body'];
        FsmtpTest::assertSame(1, substr_count($mailgun, 'name="inline"'), 'Mailgun: one inline part');
        FsmtpTest::assert(strpos($mailgun, 'name="attachment[1]"; filename="notes.pdf"') !== false, 'Mailgun: the repeat is a normal attachment');
    });

    FsmtpTest::case('inlineContentId reads only inline rows and strips header characters', function () use ($png) {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->addStringEmbeddedImage($png, 'only', 'only.png', 'base64', 'image/png');
        $rows = $mailer->getAttachments();

        FsmtpTest::assertSame('only', BaseHandler::inlineContentId($rows[0]), 'cid read');
        FsmtpTest::assertSame('', BaseHandler::inlineContentId([0 => 'x', 6 => 'attachment', 7 => 'x']), 'normal attachment');
        FsmtpTest::assertSame('ab', BaseHandler::inlineContentId([6 => 'inline', 7 => "a\r\n<\"b>"]), 'header characters removed');
        FsmtpTest::assertSame('0', BaseHandler::inlineContentId([6 => 'inline', 7 => 0]), 'cid "0" kept');
        FsmtpTest::assertSame(['a' => '0', 'b' => '', 'c' => 'x'], BaseHandler::inlineContentIds([
            'a' => [6 => 'inline', 7 => 0], 'b' => [6 => 'inline', 7 => '0'], 'c' => [6 => 'inline', 7 => 'x'],
        ]), 'a repeated cid is cleared');
    });

    FsmtpTest::case('SMTP encryption: any case of ssl/tls is sent encrypted, anything else as none', function () {
        foreach (['tls' => 'tls', 'TLS' => 'tls', ' Ssl ' => 'ssl', 'none' => 'none', 'starttls' => 'none', '' => 'none', null => 'none'] as $in => $out) {
            FsmtpTest::assertSame($out, SmtpHandler::normalizeEncryption($in), var_export($in, true));
        }
        FsmtpTest::assertSame('none', SmtpHandler::normalizeEncryption(['tls']), 'array');

        // SecretMasker rates "TLS" as encrypted; the handler now sends it encrypted too.
        $stored = ['provider' => 'smtp', 'host' => 'smtp.example.test', 'port' => '587', 'username' => 'u', 'encryption' => 'tls', 'auto_tls' => 'no'];
        FsmtpTest::assertSame(false, SecretMasker::endpointChanged(array_merge($stored, ['encryption' => 'TLS']), $stored), 'TLS is not a downgrade');
        FsmtpTest::assertSame(true, SecretMasker::endpointChanged(array_merge($stored, ['encryption' => 'starttls']), $stored), 'an unknown value is a downgrade');

        // The SMTP handler hands PHPMailer the normalized value.
        foreach (['TLS' => 'tls', 'Ssl' => 'ssl', 'none' => '', 'starttls' => ''] as $in => $out) {
            $mailer = new FsmtpSuiteSecureCapture(true);
            $mailer->setFrom('sender@example.test');
            $mailer->addAddress('to@example.test');
            $mailer->Subject = 's';
            $mailer->Body = 'b';
            try {
                (new FsmtpSuiteSmtpResultHandler())
                    ->setSettings(['provider' => 'smtp', 'sender_email' => 'sender@example.test', 'sender_name' => '', 'host' => 'smtp.invalid', 'port' => 587, 'auth' => 'no', 'encryption' => $in, 'auto_tls' => 'no', 'force_from_name' => 'no', 'force_from_email' => 'no', 'return_path' => 'no'])
                    ->setPhpMailer($mailer)
                    ->send();
            } finally {
                BaseHandler::forgetSmtpTransportClaim();
                \FluentMail\App\Hooks\Handlers\BulkSendSessionHandler::closeConnection();
            }
            FsmtpTest::assertSame($out, $mailer->sentWith, 'SMTPSecure for ' . $in);
        }
    });

    FsmtpTest::case('attachment path checks raise no warning under open_basedir', function () use ($pdf) {
        // open_basedir cannot be widened again once narrowed, so the check runs in
        // its own PHP process with stubs for the few WordPress functions it calls.
        $root = dirname(__DIR__, 2);
        $dir = rtrim(realpath(sys_get_temp_dir()), '/') . '/fsmtp-basedir-' . wp_generate_password(6, false);
        wp_mkdir_p($dir);
        file_put_contents($dir . '/inside.pdf', $pdf);
        $script = $dir . '/probe.php';
        file_put_contents($script, '<?php
            define("ABSPATH", ' . var_export(ABSPATH, true) . ');
            function __($t) { return $t; }
            function esc_html($t) { return $t; }
            function apply_filters($h, $v) { return $v; }
            require ' . var_export($root . '/vendor/autoload.php', true) . ';
            $outside = \\FluentMail\\App\\Services\\Mailer\\BaseHandler::isReadableAttachmentPath("/home/olduser/uploads/x.pdf");
            $inside = \\FluentMail\\App\\Services\\Mailer\\BaseHandler::isReadableAttachmentPath(' . var_export($dir . '/inside.pdf', true) . ');
            echo json_encode([$outside, $inside]);
        ');

        $command = escapeshellarg(PHP_BINARY)
            . ' -d display_errors=stdout -d error_reporting=' . E_ALL
            . ' -d open_basedir=' . escapeshellarg($dir . PATH_SEPARATOR . $root)
            . ' ' . escapeshellarg($script) . ' 2>&1';
        $output = (string) shell_exec($command);

        @unlink($dir . '/inside.pdf');
        @unlink($script);
        @rmdir($dir);

        FsmtpTest::assert(strpos($output, 'open_basedir') === false, 'no open_basedir warning: ' . substr($output, 0, 300));
        FsmtpTest::assertSame('[false,true]', trim($output), 'outside rejected, inside accepted');
    });

    FsmtpTest::case('Brevo: an unnamed in-memory attachment of an allow-listed type gets its extension', function () {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        foreach (['image/tiff' => '.tif', 'audio/mpeg' => '.mp3', 'video/mp4' => '.mp4', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '.pptx', 'application/pdf' => '.pdf'] as $type => $extension) {
            $row = [0 => 'data', 1 => '', 2 => '', 3 => 'base64', 4 => $type, 5 => true, 6 => 'attachment', 7 => 0];
            FsmtpTest::assertSame('attachment' . $extension, BaseHandler::attachmentName($row), $type);
        }
    });

    FsmtpTest::case('a provider written against 2.4.1 that overrides the attachment helpers still loads', function () use ($pdf) {
        // Loading the class above is the check (a static parent method would be a
        // fatal); the call shows its own calls to the 2.4.1 method still work.
        $provider = new FsmtpSuiteLegacyAttachmentProvider();
        FsmtpTest::assertSame('legacy-report.pdf', $provider->nameFor([0 => '/tmp/x.pdf', 1 => 'x.pdf', 2 => 'report.pdf', 5 => false]), 'its own call');
    });
};
