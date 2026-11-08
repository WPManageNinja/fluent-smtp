<?php

use FluentMail\App\Services\Mailer\Providers\Smtp2Go\Handler as Smtp2GoHandler;

/*
 * SMTP2GO uses an inline image's filename as its cid. Seen in live sends: Gmail
 * refused an email whose inline image was named "abc@example.com" (552, security).
 * A cid that cannot be a file name gets a generated one instead, as on Mailgun.
 */
return function () {
    FsmtpTest::requirePhpMailer();

    $png = "\x89PNG\r\n\x1a\n" . str_repeat("\x00\xff", 40);
    $pdf = "%PDF-1.4\n%%EOF";
    $image = function ($mailer, $cid, $name = 'logo.png', $type = 'image/png') use ($png) {
        $mailer->addStringEmbeddedImage($png, $cid, $name, 'base64', $type);
    };

    /* Send through SMTP2GO's handler; return the decoded request body, the parts and the mailer. */
    $send = function ($html, callable $build, $isHtml = true) {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom('sender@example.test', 'Suite');
        $mailer->addAddress('to@example.test');
        $mailer->Subject = 'smtp2go cid names';
        $mailer->isHTML($isHtml);
        $mailer->Body = $html;
        $build($mailer);

        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $request = null;

        FsmtpTest::interceptHttp(function ($url, $args) use (&$request) {
            if (strpos($url, 'api.smtp2go.com') === false) {
                return null;
            }
            $request = $args;
            return ['headers' => [], 'body' => '{"data":{"succeeded":1,"email_id":"suite"}}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
        });

        add_filter('query', $redirect, PHP_INT_MAX);
        add_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
        try {
            (new Smtp2GoHandler())
                ->setSettings(['key_store' => 'db', 'api_key' => 'suite-key', 'sender_email' => 'sender@example.test', 'sender_name' => '', 'force_from_name' => 'no', 'force_from_email' => 'no'])
                ->setPhpMailer($mailer)
                ->send();
        } finally {
            remove_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
            remove_filter('query', $redirect, PHP_INT_MAX);
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assert(is_array($request), 'a request reached SMTP2GO');
        $body = json_decode($request['body'], true);
        $rows = [];
        foreach (['inlines' => 'inline', 'attachments' => 'attachment'] as $key => $kind) {
            foreach (isset($body[$key]) ? $body[$key] : [] as $row) {
                $rows[] = [$kind, $row['filename']];
            }
        }

        return ['body' => $body, 'html' => $body['html_body'], 'parts' => $rows, 'mailer' => $mailer, 'table' => $table];
    };

    FsmtpTest::case('SMTP2GO: a cid that cannot be a file name gets a generated name, and the request HTML points at it', function () use ($send, $image) {
        foreach ([
            'com'     => ['abc@example.com', 'embed0'],
            'org'     => ['abc@example.org', 'embed0'],
            'program' => ['photo.exe', 'photo.png'],
            'space'   => ['my logo', 'logo.png'],
        ] as $label => $file) {
            $html = '<p>Signed:</p><img src="cid:' . $file[0] . '">';
            $sent = $send($html, function ($m) use ($image, $file) { $image($m, $file[0], $file[1]); });
            FsmtpTest::assertSame([['inline', 'inline-1.png']], $sent['parts'], $label . ': parts');
            FsmtpTest::assertSame('<p>Signed:</p><img src="cid:inline-1.png">', $sent['html'], $label . ': html points at the new name');
            FsmtpTest::assertSame('image/png', $sent['body']['inlines'][0]['mimetype'], $label . ': type');
            FsmtpTest::assertSame($html, $sent['mailer']->Body, $label . ': the email itself keeps its HTML');
        }
    });

    FsmtpTest::case('SMTP2GO: the log row keeps the original HTML, which a resend uses', function () use ($send, $image) {
        if (defined('FLUENTMAIL_LOG_OFF')) {
            FsmtpTest::skip('an earlier resend in this process turned logging off (FLUENTMAIL_LOG_OFF)');
            return;
        }

        global $wpdb;
        $sent = $send('<img src="cid:abc@example.com">', function ($m) use ($image) { $image($m, 'abc@example.com', 'embed0'); });
        $row = $wpdb->get_row("SELECT body FROM {$sent['table']} ORDER BY id DESC LIMIT 1", ARRAY_A);
        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $sent['html'], 'request');
        FsmtpTest::assertSame('<img src="cid:abc@example.com">', $row['body'], 'log body');
    });

    FsmtpTest::case('SMTP2GO: a multipart/alternative email is treated as HTML', function () use ($send, $image) {
        $sent = $send('<img src="cid:abc@example.com">', function ($m) use ($image) {
            $m->AltBody = 'text version';
            $m->ContentType = 'multipart/alternative';
            $image($m, 'abc@example.com', 'embed0');
        });
        FsmtpTest::assertSame([['inline', 'inline-1.png']], $sent['parts'], 'parts');
        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $sent['html'], 'html edited');
    });

    FsmtpTest::case('SMTP2GO: an email with only normal attachments sends them as before, with no inlines', function () use ($send, $pdf) {
        $html = '<p>See <img src="cid:abc@example.com"> attached.</p>';
        $sent = $send($html, function ($m) use ($pdf) {
            $m->addStringAttachment($pdf, 'terms.pdf', 'base64', 'application/pdf');
            $m->addStringAttachment('a,b', 'data@example.com.csv', 'base64', 'text/csv');
        });
        FsmtpTest::assertSame([['attachment', 'terms.pdf'], ['attachment', 'data@example.com.csv']], $sent['parts'], 'parts');
        FsmtpTest::assert(!array_key_exists('inlines', $sent['body']), 'no inlines key');
        FsmtpTest::assertSame($html, $sent['html'], 'html unchanged');
    });

    FsmtpTest::case('SMTP2GO: a cid that can be a file name goes out under it, as before', function () use ($send, $image) {
        $sent = $send('<img src="cid:logo">', function ($m) use ($image) { $image($m, 'logo'); });
        FsmtpTest::assertSame([['inline', 'logo']], $sent['parts'], 'parts');
        FsmtpTest::assertSame('<img src="cid:logo">', $sent['html'], 'html');
    });

    FsmtpTest::case('SMTP2GO: an unsafe cid used only without quotes goes as a normal attachment under its real name', function () use ($send, $image) {
        $sent = $send('<img src=cid:abc@example.com>', function ($m) use ($image) { $image($m, 'abc@example.com', 'embed0'); });
        FsmtpTest::assertSame([['attachment', 'embed0']], $sent['parts'], 'parts');
        FsmtpTest::assertSame('<img src=cid:abc@example.com>', $sent['html'], 'html');
    });

    FsmtpTest::case('SMTP2GO: a plain-text email sends an unsafe-cid image as a normal attachment and keeps its text', function () use ($send, $image) {
        $sent = $send('see <img src="cid:abc@example.com">', function ($m) use ($image) { $image($m, 'abc@example.com', 'embed0'); }, false);
        FsmtpTest::assertSame([['attachment', 'embed0']], $sent['parts'], 'parts');
        FsmtpTest::assertSame('see <img src="cid:abc@example.com">', $sent['html'], 'body unchanged');
    });

    FsmtpTest::case('SMTP2GO: an inline part that is not an image keeps its cid, as before', function () use ($send, $pdf) {
        $sent = $send('<img src="cid:terms@example.com">', function ($m) use ($pdf) { $m->addStringEmbeddedImage($pdf, 'terms@example.com', 'terms.pdf', 'base64', 'application/pdf'); });
        FsmtpTest::assertSame([['inline', 'terms@example.com']], $sent['parts'], 'parts');
    });

    FsmtpTest::case('SMTP2GO: a generated name never takes a name a kept cid uses', function () use ($send, $image) {
        $sent = $send('<img src="cid:abc@example.com"><img src=cid:inline-1.png>', function ($m) use ($image) {
            $image($m, 'abc@example.com', 'embed0');
            $image($m, 'inline-1.png', 'b.png');
        });
        FsmtpTest::assertSame([['inline', 'inline-2.png'], ['inline', 'inline-1.png']], $sent['parts'], 'parts');
    });

    FsmtpTest::case('SMTP2GO: a normal attachment beside a renamed image keeps its name', function () use ($send, $image, $pdf) {
        $sent = $send('<img src="cid:abc@example.com">', function ($m) use ($image, $pdf) {
            $image($m, 'abc@example.com', 'embed0');
            $m->addStringAttachment($pdf, 'terms.pdf', 'base64', 'application/pdf');
        });
        FsmtpTest::assertSame([['inline', 'inline-1.png'], ['attachment', 'terms.pdf']], $sent['parts'], 'parts');
    });

    FsmtpTest::case('SMTP2GO: an image that cannot be read keeps its cid in the HTML', function () use ($send, $png) {
        $path = wp_upload_dir()['basedir'] . '/' . FsmtpTest::uniq('fsmtp-gone') . '.png';
        file_put_contents($path, $png);
        $sent = $send('<img src="cid:abc@example.com">', function ($m) use ($path) {
            $m->addEmbeddedImage($path, 'abc@example.com', 'embed0', 'base64', 'image/png');
            unlink($path);
        });
        FsmtpTest::assertSame([], $sent['parts'], 'parts');
        FsmtpTest::assertSame('<img src="cid:abc@example.com">', $sent['html'], 'html');
    });
};
