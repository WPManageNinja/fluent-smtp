<?php

use FluentMail\App\Services\Mailer\Providers\Mailgun\Handler as MailgunHandler;

/*
 * Mailgun uses an inline part's file name as its Content-ID and types the part
 * from that name, ignoring the type sent. Seen in live sends: an image under the
 * cid "abc@example.com" went out as application/x-msdownload and Gmail refused the
 * email. A cid that cannot be a file name gets a generated one instead.
 */
return function () {
    FsmtpTest::requirePhpMailer();

    $png = "\x89PNG\r\n\x1a\n" . str_repeat("\x00\xff", 40);
    $pdf = "%PDF-1.4\n%%EOF";
    $image = function ($mailer, $cid, $name = 'logo.png', $type = 'image/png') use ($png) {
        $mailer->addStringEmbeddedImage($png, $cid, $name, 'base64', $type);
    };

    /* Send through Mailgun's handler; return the request body, the parts and the mailer. */
    $send = function ($html, callable $build, $isHtml = true) {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom('sender@example.test', 'Suite');
        $mailer->addAddress('to@example.test');
        $mailer->Subject = 'mailgun cid names';
        $mailer->isHTML($isHtml);
        $mailer->Body = $html;
        $build($mailer);

        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $request = null;

        FsmtpTest::interceptHttp(function ($url, $args) use (&$request) {
            if (strpos($url, 'api.mailgun.net') === false) {
                return null;
            }
            $request = $args;
            return ['headers' => [], 'body' => '{"id":"<suite@example.test>","message":"Queued."}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
        });

        add_filter('query', $redirect, PHP_INT_MAX);
        add_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
        try {
            (new MailgunHandler())
                ->setSettings(['key_store' => 'db', 'api_key' => 'suite-key', 'domain_name' => 'mg.example.test', 'region' => 'us', 'sender_email' => 'sender@example.test', 'sender_name' => '', 'force_from_name' => 'no', 'force_from_email' => 'no'])
                ->setPhpMailer($mailer)
                ->send();
        } finally {
            remove_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
            remove_filter('query', $redirect, PHP_INT_MAX);
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assert(is_array($request), 'a request reached Mailgun');
        // With no readable attachment the request goes as plain form fields.
        $body = is_array($request['body']) ? (string) (isset($request['body']['html']) ? $request['body']['html'] : '') : (string) $request['body'];
        preg_match_all('/name="(inline|attachment\[\d+\])"; filename="([^"]*)"/', $body, $parts, PREG_SET_ORDER);
        $rows = array_map(function ($part) {
            return [strpos($part[1], 'inline') === 0 ? 'inline' : 'attachment', $part[2]];
        }, $parts);

        return ['body' => $body, 'parts' => $rows, 'mailer' => $mailer, 'table' => $table];
    };

    FsmtpTest::case('Mailgun: a cid that cannot be a file name gets a generated name, and the request HTML points at it', function () use ($send, $image) {
        foreach ([
            'com'     => ['abc@example.com', 'embed0'],
            'org'     => ['abc@example.org', 'embed0'],
            'program' => ['photo.exe', 'photo.png'],
            'space'   => ['my logo', 'logo.png'],
        ] as $label => $file) {
            $html = '<p>Signed:</p><img src="cid:' . $file[0] . '">';
            $sent = $send($html, function ($m) use ($image, $file) { $image($m, $file[0], $file[1]); });
            FsmtpTest::assertSame([['inline', 'inline-1.png']], $sent['parts'], $label . ': parts');
            FsmtpTest::assert(strpos($sent['body'], '<img src="cid:inline-1.png">') !== false, $label . ': html points at the new name');
            FsmtpTest::assert(strpos($sent['body'], 'cid:' . $file[0]) === false, $label . ': old cid gone from the request');
            FsmtpTest::assertSame($html, $sent['mailer']->Body, $label . ': the email itself keeps its HTML');
        }
    });

    FsmtpTest::case('Mailgun: the log row keeps the original HTML, which a resend uses', function () use ($send, $image) {
        if (defined('FLUENTMAIL_LOG_OFF')) {
            FsmtpTest::skip('an earlier resend in this process turned logging off (FLUENTMAIL_LOG_OFF)');
            return;
        }

        global $wpdb;
        $sent = $send('<img src="cid:abc@example.com">', function ($m) use ($image) { $image($m, 'abc@example.com', 'embed0'); });
        $row = $wpdb->get_row("SELECT body FROM {$sent['table']} ORDER BY id DESC LIMIT 1", ARRAY_A);
        FsmtpTest::assert(strpos($sent['body'], '<img src="cid:inline-1.png">') !== false, 'request');
        FsmtpTest::assertSame('<img src="cid:abc@example.com">', $row['body'], 'log body');
    });

    FsmtpTest::case('Mailgun: with a plain-text alternative, only the HTML part is edited', function () use ($send, $image) {
        $sent = $send('<img src="cid:abc@example.com">', function ($m) use ($image) {
            $m->AltBody = 'text with <img src="cid:abc@example.com">';
            $m->ContentType = 'multipart/alternative';
            $image($m, 'abc@example.com', 'embed0');
        });
        FsmtpTest::assertSame([['inline', 'inline-1.png']], $sent['parts'], 'parts');
        FsmtpTest::assert(strpos($sent['body'], '<img src="cid:inline-1.png">') !== false, 'html edited');
        FsmtpTest::assert(strpos($sent['body'], 'text with <img src="cid:abc@example.com">') !== false, 'text left alone');
    });

    FsmtpTest::case('Mailgun: a cid that can be a file name goes out under it, as before', function () use ($send, $image) {
        $sent = $send('<img src="cid:logo">', function ($m) use ($image) { $image($m, 'logo'); });
        FsmtpTest::assertSame([['inline', 'logo']], $sent['parts'], 'parts');
        FsmtpTest::assert(strpos($sent['body'], '<img src="cid:logo">') !== false, 'html');
    });

    FsmtpTest::case('Mailgun: an unsafe cid used only without quotes goes as a normal attachment under its real name', function () use ($send, $image) {
        $sent = $send('<img src=cid:abc@example.com>', function ($m) use ($image) { $image($m, 'abc@example.com', 'embed0'); });
        FsmtpTest::assertSame([['attachment', 'embed0']], $sent['parts'], 'parts');
        FsmtpTest::assert(strpos($sent['body'], '<img src=cid:abc@example.com>') !== false, 'html');
    });

    FsmtpTest::case('Mailgun: a plain-text email sends an unsafe-cid image as a normal attachment', function () use ($send, $image) {
        $sent = $send('see <img src="cid:abc@example.com">', function ($m) use ($image) { $image($m, 'abc@example.com', 'embed0'); }, false);
        FsmtpTest::assertSame([['attachment', 'embed0']], $sent['parts'], 'parts');
    });

    FsmtpTest::case('Mailgun: an inline part that is not an image keeps its cid, as before', function () use ($send, $pdf) {
        $sent = $send('<img src="cid:terms@example.com">', function ($m) use ($pdf) { $m->addStringEmbeddedImage($pdf, 'terms@example.com', 'terms.pdf', 'base64', 'application/pdf'); });
        FsmtpTest::assertSame([['inline', 'terms@example.com']], $sent['parts'], 'parts');
    });

    FsmtpTest::case('Mailgun: a generated name never takes a name a kept cid uses', function () use ($send, $image) {
        $sent = $send('<img src="cid:abc@example.com"><img src=cid:inline-1.png>', function ($m) use ($image) {
            $image($m, 'abc@example.com', 'embed0');
            $image($m, 'inline-1.png', 'b.png');
        });
        FsmtpTest::assertSame([['inline', 'inline-2.png'], ['inline', 'inline-1.png']], $sent['parts'], 'parts');
    });

    FsmtpTest::case('Mailgun: an image that cannot be read keeps its cid in the HTML', function () use ($send, $png) {
        $path = wp_upload_dir()['basedir'] . '/' . FsmtpTest::uniq('fsmtp-gone') . '.png';
        file_put_contents($path, $png);
        $sent = $send('<img src="cid:abc@example.com">', function ($m) use ($path) {
            $m->addEmbeddedImage($path, 'abc@example.com', 'embed0', 'base64', 'image/png');
            unlink($path);
        });
        FsmtpTest::assertSame([], $sent['parts'], 'parts');
        FsmtpTest::assert(strpos($sent['body'], '<img src="cid:abc@example.com">') !== false, 'html');
    });
};
