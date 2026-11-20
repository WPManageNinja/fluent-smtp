<?php

use FluentMail\App\Services\Mailer\Providers\ElasticMail\Handler as ElasticHandler;
use FluentMail\App\Services\Mailer\Providers\SendInBlue\Handler as BrevoHandler;

/*
 * Brevo and Elastic Email have no inline field, but embed an attachment when the
 * HTML's cid equals its file name. Undocumented: read from live sends with real
 * accounts in Gmail and Thunderbird, not from their API references. Brevo refuses
 * a name with no allowed extension, so its request HTML is edited for every image
 * whose cid is not already its file name; Elastic Email's only for a cid that
 * cannot be a file name.
 */
return function () {
    FsmtpTest::requirePhpMailer();

    $png = "\x89PNG\r\n\x1a\n" . str_repeat("\x00\xff", 40);
    $pdf = "%PDF-1.4\n%%EOF";
    $image = function ($mailer, $cid, $name = 'logo.png', $type = 'image/png') use ($png) {
        $mailer->addStringEmbeddedImage($png, $cid, $name, 'base64', $type);
    };

    /* Send one email through a handler; return the request args. */
    $send = function ($class, $url, $status, $body, $html, callable $build, $isHtml = true, $handler = null) {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom('sender@example.test', 'Suite');
        $mailer->addAddress('to@example.test');
        $mailer->Subject = 'inline by name';
        $mailer->isHTML($isHtml);
        $mailer->Body = $html;
        $build($mailer);

        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $request = null;

        FsmtpTest::interceptHttp(function ($requested, $args) use (&$request, $url, $status, $body) {
            if (strpos($requested, $url) !== 0) {
                return null;
            }
            $request = $args;
            return ['headers' => [], 'body' => $body, 'response' => ['code' => $status, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
        });

        add_filter('query', $redirect, PHP_INT_MAX);
        add_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
        try {
            ($handler ? $handler : new $class())
                ->setSettings(['key_store' => 'db', 'api_key' => 'suite-key', 'sender_email' => 'sender@example.test', 'sender_name' => '', 'force_from_name' => 'no', 'mail_type' => 'transactional'])
                ->setPhpMailer($mailer)
                ->send();
        } finally {
            remove_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
            remove_filter('query', $redirect, PHP_INT_MAX);
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assert(is_array($request), 'a request reached ' . $url);
        $request['suite_table'] = $table;
        $request['suite_mailer'] = $mailer;

        return $request;
    };

    $brevoRequest = function ($html, callable $build, $isHtml = true) use ($send) {
        return $send(BrevoHandler::class, 'https://api.brevo.com/', 201, '{"messageId":"<suite@example.test>"}', $html, $build, $isHtml);
    };
    $brevo = function ($html, callable $build, $isHtml = true) use ($brevoRequest) {
        return json_decode($brevoRequest($html, $build, $isHtml)['body'], true);
    };
    $brevoNames = function ($body) {
        return array_column(isset($body['attachment']) ? $body['attachment'] : [], 'name');
    };

    $elastic = function ($html, callable $build, $isHtml = true) use ($send) {
        $request = $send(ElasticHandler::class, 'https://api.elasticemail.com/', 200, '{"success":true,"data":{"messageid":"suite"}}', $html, $build, $isHtml);
        $body = (string) $request['body'];
        preg_match_all('/name="attachments(\d+)"; filename="([^"]*)"/', $body, $names);
        return ['body' => $body, 'names' => $names[2], 'fields' => $names[1], 'table' => $request['suite_table']];
    };

    FsmtpTest::case('Brevo: a quoted src is pointed at a generated name, and the attachment carries that name', function () use ($brevo, $brevoNames, $image, $png) {
        $body = $brevo('<p><img src="cid:logo"></p>', function ($m) use ($image) { $image($m, 'logo'); });

        FsmtpTest::assertSame('<p><img src="cid:inline-1.png"></p>', $body['htmlContent'], 'html');
        FsmtpTest::assertSame(['inline-1.png'], $brevoNames($body), 'names');
        FsmtpTest::assertSame(base64_encode($png), $body['attachment'][0]['content'], 'bytes');
    });

    FsmtpTest::case('Brevo: quotes, capitals and spaces around = are written back as src="...", and other attributes are kept', function () use ($brevo, $image) {
        $build = function ($m) use ($image) { $image($m, 'logo'); };

        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $brevo("<img src='cid:logo'>", $build)['htmlContent'], 'single quotes');
        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $brevo('<img SRC="CID:logo">', $build)['htmlContent'], 'capitals');
        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $brevo('<img src = "cid:logo">', $build)['htmlContent'], 'spaces');
        FsmtpTest::assertSame('<IMG alt="a" src="cid:inline-1.png" width="9">', $brevo('<IMG alt="a" src="cid:logo" width="9">', $build)['htmlContent'], 'attributes');
        FsmtpTest::assertSame("<img\nsrc=\"cid:inline-1.png\">", $brevo("<img\nsrc=\"cid:logo\">", $build)['htmlContent'], 'new line');
    });

    FsmtpTest::case('Brevo: cids are compared whole and with their case', function () use ($brevo, $brevoNames, $image) {
        $body = $brevo('<img src="cid:logo"><img src="cid:logo2"><img src="cid:Logo">', function ($m) use ($image) {
            $image($m, 'logo', 'a.png');
            $image($m, 'logo2', 'b.png');
            $image($m, 'Logo', 'c.png');
        });

        FsmtpTest::assertSame('<img src="cid:inline-1.png"><img src="cid:inline-2.png"><img src="cid:inline-3.png">', $body['htmlContent'], 'html');
        FsmtpTest::assertSame(['inline-1.png', 'inline-2.png', 'inline-3.png'], $brevoNames($body), 'names');
    });

    FsmtpTest::case('Brevo: one image shown twice is pointed at the same name; a repeated cid stays a normal attachment', function () use ($brevo, $brevoNames, $image) {
        $body = $brevo('<img src="cid:logo"><img src="cid:logo">', function ($m) use ($image) {
            $image($m, 'logo', 'first.png');
            $image($m, 'logo', 'second.png');
        });

        FsmtpTest::assertSame('<img src="cid:inline-1.png"><img src="cid:inline-1.png">', $body['htmlContent'], 'html');
        FsmtpTest::assertSame(['inline-1.png', 'second.png'], $brevoNames($body), 'names');
    });

    FsmtpTest::case('Brevo: a cid of 0, an image after a PDF, and a name with capitals', function () use ($brevo, $brevoNames, $image, $pdf) {
        $body = $brevo('<img src="cid:0">', function ($m) use ($image, $pdf) {
            $m->addStringAttachment($pdf, 'report.pdf', 'base64', 'application/pdf');
            $image($m, '0', 'LOGO.PNG');
        });

        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $body['htmlContent'], 'html');
        FsmtpTest::assertSame(['report.pdf', 'inline-1.png'], $brevoNames($body), 'names');
    });

    FsmtpTest::case('Brevo: generated names avoid a normal attachment of that name, before or after the image', function () use ($brevo, $brevoNames, $image, $pdf) {
        $before = $brevo('<img src="cid:logo">', function ($m) use ($image, $pdf) {
            $m->addStringAttachment($pdf, 'inline-1.png', 'base64', 'image/png');
            $image($m, 'logo');
        });
        FsmtpTest::assertSame('<img src="cid:inline-2.png">', $before['htmlContent'], 'before: html');
        FsmtpTest::assertSame(['inline-1.png', 'inline-2.png'], $brevoNames($before), 'before: names');

        $after = $brevo('<img src="cid:logo">', function ($m) use ($image, $pdf) {
            $image($m, 'logo');
            $m->addStringAttachment($pdf, 'inline-1.png', 'base64', 'image/png');
        });
        FsmtpTest::assertSame('<img src="cid:inline-2.png">', $after['htmlContent'], 'after: html');
        FsmtpTest::assertSame(['inline-2.png', 'inline-1.png'], $brevoNames($after), 'after: names');
    });

    FsmtpTest::case('Brevo: a caller cid that looks generated cannot chain into another image', function () use ($brevo, $brevoNames, $image) {
        $body = $brevo('<img src="cid:logo"><img src="cid:inline-1.png">', function ($m) use ($image) {
            $image($m, 'logo', 'a.png');
            $image($m, 'inline-1.png', 'b.png');
        });

        FsmtpTest::assertSame('<img src="cid:inline-1.png"><img src="cid:inline-2.png">', $body['htmlContent'], 'html');
        FsmtpTest::assertSame(['inline-1.png', 'inline-2.png'], $brevoNames($body), 'names');
    });

    FsmtpTest::case('Brevo: a cid equal to the file name is left alone', function () use ($brevo, $brevoNames, $image) {
        $capitals = $brevo('<img src="cid:LOGO.PNG">', function ($m) use ($image) { $image($m, 'LOGO.PNG', 'LOGO.PNG'); });
        FsmtpTest::assertSame('<img src="cid:LOGO.PNG">', $capitals['htmlContent'], 'capitals: html');
        FsmtpTest::assertSame(['LOGO.PNG'], $brevoNames($capitals), 'capitals: names');

        $alone = $brevo('<img src="cid:logo.png">', function ($m) use ($image) { $image($m, 'logo.png'); });
        FsmtpTest::assertSame('<img src="cid:logo.png">', $alone['htmlContent'], 'alone: html');
        FsmtpTest::assertSame(['logo.png'], $brevoNames($alone), 'alone: names');
    });

    FsmtpTest::case('Brevo: an empty cid does not hide the image after it', function () use ($brevo, $image) {
        $body = $brevo('<img src="cid:"><img src="cid:logo">', function ($m) use ($image) { $image($m, 'logo'); });
        FsmtpTest::assertSame('<img src="cid:"><img src="cid:inline-1.png">', $body['htmlContent'], 'html');
    });

    FsmtpTest::case('Brevo: the bytes of an in-memory image are not read as a path', function () use ($brevo, $brevoNames, $png) {
        $body = $brevo('<img src="cid:logo">', function ($m) use ($png) { $m->addStringEmbeddedImage($png . '.exe', 'logo', 'a.png', 'base64', 'image/png'); });
        FsmtpTest::assertSame(['inline-1.png'], $brevoNames($body), 'names');
    });

    FsmtpTest::case('Brevo: a cid equal to the name is only left alone where that embeds', function () use ($brevo, $brevoNames, $image) {
        $bare = $brevo('<img src="cid:logo">', function ($m) use ($image) { $image($m, 'logo', 'logo', 'image/png'); });
        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $bare['htmlContent'], 'no extension: html');
        FsmtpTest::assertSame(['inline-1.png'], $brevoNames($bare), 'no extension: names');

        $mixed = $brevo('<img src="cid:logo.png"><img src=\'cid:logo.png\'>', function ($m) use ($image) { $image($m, 'logo.png', 'logo.png'); });
        FsmtpTest::assertSame('<img src="cid:inline-1.png"><img src="cid:inline-1.png">', $mixed['htmlContent'], 'mixed quotes: html');

        $reversed = $brevo('<img src=\'cid:logo.png\'><img src="cid:logo.png">', function ($m) use ($image) { $image($m, 'logo.png', 'logo.png'); });
        FsmtpTest::assertSame('<img src="cid:inline-1.png"><img src="cid:inline-1.png">', $reversed['htmlContent'], 'mixed quotes, reversed: html');

        foreach (["<img src='cid:logo.png'>", '<img src = "cid:logo.png">', '<img SRC="CID:logo.png">'] as $html) {
            $body = $brevo($html, function ($m) use ($image) { $image($m, 'logo.png', 'logo.png'); });
            FsmtpTest::assertSame('<img src="cid:inline-1.png">', $body['htmlContent'], $html);
            FsmtpTest::assertSame(['inline-1.png'], $brevoNames($body), $html . ': names');
        }
    });

    FsmtpTest::case('Brevo: every form that does not embed goes out byte for byte, under the real name', function () use ($brevo, $brevoNames, $image) {
        foreach ([
            'unquoted'  => '<img src=cid:logo>',
            'css'       => '<div style="background:url(cid:logo)"></div>',
            'data-src'  => '<img data-src="cid:logo">',
            'srcset'    => '<img srcset="cid:logo 1x">',
            'alt'       => '<img src="x.png" alt="cid:logo">',
            'text'      => '<p>cid:logo</p>',
            'unrelated' => '<img src="cid:other">',
            'not-img'   => '<video src="cid:logo"></video>',
            'prose'     => '<p>use src="cid:logo" here</p>',
        ] as $label => $html) {
            $body = $brevo($html, function ($m) use ($image) { $image($m, 'logo'); });
            FsmtpTest::assertSame($html, $body['htmlContent'], $label . ': html');
            FsmtpTest::assertSame(['logo.png'], $brevoNames($body), $label . ': names');
        }
    });

    FsmtpTest::case('Brevo: a cid with a space is pointed at a generated name', function () use ($brevo, $brevoNames, $image) {
        $spaced = $brevo('<img src="cid:my logo">', function ($m) use ($image) { $image($m, 'my logo'); });
        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $spaced['htmlContent'], 'html');
        FsmtpTest::assertSame(['inline-1.png'], $brevoNames($spaced), 'names');
    });

    FsmtpTest::case('Brevo: an image with no extension takes one from its type', function () use ($brevo, $brevoNames, $image) {
        foreach ([
            'postnl style' => ['postnl-smart-return-printcode', 'image/png', 'png'],
            'data uri'     => ['embed0', 'image/png', 'png'],
            'jpeg'         => ['photo', 'image/jpeg', 'jpg'],
        ] as $label => $file) {
            $body = $brevo('<img src="cid:abc@example.com">', function ($m) use ($image, $file) { $image($m, 'abc@example.com', $file[0], $file[1]); });
            FsmtpTest::assertSame('<img src="cid:inline-1.' . $file[2] . '">', $body['htmlContent'], $label . ': html');
            FsmtpTest::assertSame(['inline-1.' . $file[2]], $brevoNames($body), $label . ': names');
        }
    });

    FsmtpTest::case('Brevo: a file on disk with a bare name takes the extension of its type', function () use ($brevo, $brevoNames, $png) {
        $path = wp_upload_dir()['basedir'] . '/' . FsmtpTest::uniq('fsmtp-noext') . '.png';
        file_put_contents($path, $png);

        $body = $brevo('<img src="cid:logo">', function ($m) use ($path) { $m->addEmbeddedImage($path, 'logo', 'Rechnung', 'base64', 'image/png'); });
        unlink($path);

        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $body['htmlContent'], 'html');
        FsmtpTest::assertSame(['inline-1.png'], $brevoNames($body), 'names');
    });

    FsmtpTest::case('Brevo: anything that is not plainly an image keeps its name and its cid', function () use ($brevo, $brevoNames, $image, $pdf) {
        $inlinePdf = $brevo('<img src="cid:terms">', function ($m) use ($pdf) { $m->addStringEmbeddedImage($pdf, 'terms', 'terms.pdf', 'base64', 'application/pdf'); });
        FsmtpTest::assertSame('<img src="cid:terms">', $inlinePdf['htmlContent'], 'pdf: html');
        FsmtpTest::assertSame(['terms.pdf'], $brevoNames($inlinePdf), 'pdf: names');

        foreach ([
            'declared image, exe name' => ['x.exe', 'image/png'],
            'svg'                      => ['logo.svg', 'image/svg+xml'], // PHPMailer's type table has no svg, so it is not read as an image
            'dotted name'              => ['Logo v1.2', 'image/png'],
            'no type at all'           => ['logo', 'application/octet-stream'],
            'unknown image type'       => ['picture', 'image/avif'],
            'declared as a document'   => ['scan.png', 'application/pdf'],
        ] as $label => $file) {
            $body = $brevo('<img src="cid:pic">', function ($m) use ($image, $file) { $image($m, 'pic', $file[0], $file[1]); });
            FsmtpTest::assertSame('<img src="cid:pic">', $body['htmlContent'], $label . ': html');
            FsmtpTest::assert(!in_array('inline-1.png', $brevoNames($body), true), $label . ': no generated name');
        }
    });

    FsmtpTest::case('Brevo: a file on disk that is no image is not renamed, whatever it declares', function () use ($brevoRequest, $png) {
        $path = wp_upload_dir()['basedir'] . '/' . FsmtpTest::uniq('fsmtp-prog') . '.exe';
        file_put_contents($path, $png);

        $request = $brevoRequest('<img src="cid:logo">', function ($m) use ($path) { $m->addEmbeddedImage($path, 'logo', 'picture', 'base64', 'image/png'); });
        unlink($path);
        $body = json_decode($request['body'], true);

        FsmtpTest::assertSame('<img src="cid:logo">', $body['htmlContent'], 'html');
        FsmtpTest::assert(strpos($request['body'], 'inline-1') === false, 'no generated name');
    });

    FsmtpTest::case('Brevo: a handler reused for a plain-text email keeps no names from the last send', function () use ($send, $image) {
        $handler = new BrevoHandler();
        $reply = '{"messageId":"<suite@example.test>"}';
        $build = function ($m) use ($image) { $image($m, 'logo'); };

        $send(BrevoHandler::class, 'https://api.brevo.com/', 201, $reply, '<img src="cid:logo">', $build, true, $handler);
        $second = json_decode($send(BrevoHandler::class, 'https://api.brevo.com/', 201, $reply, 'plain', $build, false, $handler)['body'], true);

        FsmtpTest::assertSame(['logo.png'], array_column($second['attachment'], 'name'), 'names');
    });

    FsmtpTest::case('Brevo: an image that cannot be read keeps its cid in the HTML, and the next image still works', function () use ($brevo, $brevoNames, $image, $png) {
        $path = wp_upload_dir()['basedir'] . '/' . FsmtpTest::uniq('fsmtp-gone') . '.png';
        file_put_contents($path, $png);

        $body = $brevo('<img src="cid:gone"><img src="cid:logo">', function ($m) use ($image, $path) {
            $m->addEmbeddedImage($path, 'gone', 'gone.png');
            $image($m, 'logo');
            unlink($path);
        });

        FsmtpTest::assertSame('<img src="cid:gone"><img src="cid:inline-2.png">', $body['htmlContent'], 'html');
        FsmtpTest::assertSame(['inline-2.png'], $brevoNames($body), 'names');
    });

    FsmtpTest::case('Brevo: a plain-text email renames nothing and regains no HTML', function () use ($brevo, $brevoNames, $image) {
        $body = $brevo('<img src="cid:logo">', function ($m) use ($image) { $image($m, 'logo'); }, false);

        FsmtpTest::assert(!array_key_exists('htmlContent', $body), 'no htmlContent');
        FsmtpTest::assertSame('<img src="cid:logo">', $body['textContent'], 'text');
        FsmtpTest::assertSame(['logo.png'], $brevoNames($body), 'names');
    });

    FsmtpTest::case('Brevo: the plain-text alternative is not edited', function () use ($brevo, $image) {
        $body = $brevo('<img src="cid:logo">', function ($m) use ($image) {
            $m->AltBody = 'alt with <img src="cid:logo">';
            $m->ContentType = 'multipart/alternative';
            $image($m, 'logo');
        });

        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $body['htmlContent'], 'html');
        FsmtpTest::assertSame('alt with <img src="cid:logo">', $body['textContent'], 'text');
    });

    FsmtpTest::case('Brevo: the request is edited while the log row and the PHPMailer object keep the original', function () use ($brevoRequest, $image) {
        if (defined('FLUENTMAIL_LOG_OFF')) {
            FsmtpTest::skip('an earlier resend in this process turned logging off (FLUENTMAIL_LOG_OFF)');
            return;
        }

        global $wpdb;
        $request = $brevoRequest('<img src="cid:logo">', function ($m) use ($image) { $image($m, 'logo'); });
        $sent = json_decode($request['body'], true);
        $row = $wpdb->get_row("SELECT body, attachments FROM {$request['suite_table']} ORDER BY id DESC LIMIT 1", ARRAY_A);

        FsmtpTest::assertSame('<img src="cid:inline-1.png">', $sent['htmlContent'], 'request');
        FsmtpTest::assertSame('<img src="cid:logo">', $row['body'], 'log body');
        FsmtpTest::assert(strpos($row['attachments'], 'inline-1.png') === false, 'log keeps the real name');
        FsmtpTest::assertSame('<img src="cid:logo">', $request['suite_mailer']->Body, 'PHPMailer body');
        FsmtpTest::assertSame('logo.png', $request['suite_mailer']->getAttachments()[0][2], 'PHPMailer name');
    });

    FsmtpTest::case('Brevo: an email with attachments but no inline image keeps its HTML, fields and order', function () use ($brevo, $pdf) {
        $body = $brevo('<p>hello</p>', function ($m) use ($pdf) { $m->addStringAttachment($pdf, 'report.pdf', 'base64', 'application/pdf'); });

        FsmtpTest::assertSame('<p>hello</p>', $body['htmlContent'], 'html');
        FsmtpTest::assertSame(['sender', 'subject', 'htmlContent', 'to', 'attachment'], array_keys($body), 'key order');
    });

    FsmtpTest::case('Elastic Email: a shown image is named by its cid and the HTML is sent exactly as given', function () use ($elastic, $image, $png) {
        $html = '<p><img src="cid:logo"></p>';
        $sent = $elastic($html, function ($m) use ($image) { $image($m, 'logo'); });

        FsmtpTest::assertSame(['logo'], $sent['names'], 'names');
        FsmtpTest::assert(strpos($sent['body'], $html) !== false, 'html unchanged');
        FsmtpTest::assert(strpos($sent['body'], $png) !== false, 'bytes');
    });

    FsmtpTest::case('Elastic Email: a cid of 0, and logo beside logo2', function () use ($elastic, $image) {
        FsmtpTest::assertSame(['0'], $elastic('<img src="cid:0">', function ($m) use ($image) { $image($m, '0'); })['names'], 'zero');
        FsmtpTest::assertSame(['logo', 'logo2'], $elastic('<img src="cid:logo"><img src="cid:logo2">', function ($m) use ($image) {
            $image($m, 'logo', 'a.png');
            $image($m, 'logo2', 'b.png');
        })['names'], 'prefix');
    });

    FsmtpTest::case('Elastic Email: every way of writing a src names the image by its cid, and the HTML is never changed', function () use ($elastic, $image) {
        foreach ([
            'capitals'      => '<img src="CID:logo">',
            'single quotes' => "<img src='cid:logo'>",
            'spaces'        => '<img src = "cid:logo">',
            'unquoted'      => '<img src=cid:logo>',
            'unquoted, attributes' => '<img src=cid:logo alt=x>',
        ] as $label => $html) {
            $sent = $elastic($html, function ($m) use ($image) { $image($m, 'logo'); });
            FsmtpTest::assertSame(['logo'], $sent['names'], $label);
            FsmtpTest::assert(strpos($sent['body'], $html) !== false, $label . ': html');
        }
    });

    FsmtpTest::case('Elastic Email: an image no src shows keeps its real name', function () use ($elastic, $image) {
        foreach ([
            'other cid' => '<img src="cid:other">',
            'prefix'    => '<img src="cid:logo2">',
            'css'       => '<div style="background:url(cid:logo)"></div>',
            'alt'       => '<img src="x.png" alt="cid:logo">',
            'data-src'  => '<img data-src="cid:logo">',
            'prose'     => '<p>see cid:logo</p>',
        ] as $label => $html) {
            FsmtpTest::assertSame(['logo.png'], $elastic($html, function ($m) use ($image) { $image($m, 'logo'); })['names'], $label);
        }
    });

    FsmtpTest::case('Elastic Email: a cid that cannot be a file name gets a generated name, and the request HTML points at it', function () use ($elastic, $image) {
        foreach ([
            'space'        => ['my logo', 'logo.png'],
            'backslash'    => ['a\\b', 'logo.png'],
            'com'          => ['abc@example.com', 'embed0'],
            'capitals'     => ['ABC@EXAMPLE.COM', 'embed0'],
            'program'      => ['photo.exe', 'photo.png'],
            'org'          => ['abc@example.org', 'embed0'],
            'unknown'      => ['abc@phpmailer.0', 'embed0'],
            'other image'  => ['photo.gif', 'photo.png'],
            'trailing dot' => ['photo.exe.', 'photo.png'],
            'slash'        => ['photo.exe/x', 'photo.png'],
        ] as $label => $file) {
            $sent = $elastic('<img src="cid:' . $file[0] . '">', function ($m) use ($image, $file) { $image($m, $file[0], $file[1]); });
            FsmtpTest::assertSame(['inline-1.png'], $sent['names'], $label . ': names');
            FsmtpTest::assert(strpos($sent['body'], '<img src="cid:inline-1.png">') !== false, $label . ': html points at the new name');
            FsmtpTest::assert(strpos($sent['body'], 'cid:' . $file[0]) === false, $label . ': old cid gone from the request');
        }
    });

    FsmtpTest::case('Elastic Email: a cid that can be a file name is used as it is', function () use ($elastic, $image) {
        foreach (['logo.png' => 'a.png', 'LOGO.PNG' => 'a.png', 'x.jpeg' => null] as $cid => $name) {
            $sent = $elastic('<img src="cid:' . $cid . '">', function ($m) use ($image, $cid, $name) {
                $image($m, $cid, $name === null ? 'a.jpg' : $name, $name === null ? 'image/jpeg' : 'image/png');
            });
            FsmtpTest::assertSame([$cid], $sent['names'], $cid);
            FsmtpTest::assert(strpos($sent['body'], '<img src="cid:' . $cid . '">') !== false, $cid . ': html unchanged');
        }
    });

    FsmtpTest::case('Elastic Email: each part keeps its own field number, even when one before it cannot be read', function () use ($elastic, $image, $png) {
        $path = wp_upload_dir()['basedir'] . '/' . FsmtpTest::uniq('fsmtp-gone') . '.png';
        file_put_contents($path, $png);
        $sent = $elastic('<img src="cid:a"><img src="cid:b"><img src="cid:c">', function ($m) use ($image, $path) {
            $image($m, 'a', 'a.png');
            $m->addEmbeddedImage($path, 'b', 'b.png', 'base64', 'image/png');
            unlink($path);
            $image($m, 'c', 'c.png');
        });
        FsmtpTest::assertSame(['a', 'c'], $sent['names'], 'names');
        FsmtpTest::assertSame(['1', '3'], $sent['fields'], 'field numbers');
    });

    FsmtpTest::case('Elastic Email: the log row keeps the original HTML, which a resend uses', function () use ($elastic, $image) {
        if (defined('FLUENTMAIL_LOG_OFF')) {
            FsmtpTest::skip('an earlier resend in this process turned logging off (FLUENTMAIL_LOG_OFF)');
            return;
        }

        global $wpdb;
        $sent = $elastic('<img src="cid:abc@example.com">', function ($m) use ($image) { $image($m, 'abc@example.com', 'embed0'); });
        $row = $wpdb->get_row("SELECT body FROM {$sent['table']} ORDER BY id DESC LIMIT 1", ARRAY_A);
        FsmtpTest::assert(strpos($sent['body'], '<img src="cid:inline-1.png">') !== false, 'request');
        FsmtpTest::assertSame('<img src="cid:abc@example.com">', $row['body'], 'log body');
    });

    FsmtpTest::case('Elastic Email: with a plain-text alternative, only the HTML part is edited', function () use ($elastic, $image) {
        $sent = $elastic('<img src="cid:abc@example.com">', function ($m) use ($image) {
            $m->AltBody = 'text with <img src="cid:abc@example.com">';
            $m->ContentType = 'multipart/alternative';
            $image($m, 'abc@example.com', 'embed0');
        });
        FsmtpTest::assertSame(['inline-1.png'], $sent['names'], 'names');
        FsmtpTest::assert(strpos($sent['body'], 'text with <img src="cid:abc@example.com">') !== false, 'text left alone');
        FsmtpTest::assert(strpos($sent['body'], '<img src="cid:inline-1.png">') !== false, 'html edited');
    });

    FsmtpTest::case('Elastic Email: a generated name never takes a name a kept cid uses', function () use ($elastic, $image) {
        $sent = $elastic('<img src="cid:abc@example.com"><img src=cid:inline-1.png>', function ($m) use ($image) {
            $image($m, 'abc@example.com', 'embed0');
            $image($m, 'inline-1.png', 'b.png');
        });
        FsmtpTest::assertSame(['inline-2.png', 'inline-1.png'], $sent['names'], 'names');
        FsmtpTest::assert(strpos($sent['body'], '<img src="cid:inline-2.png"><img src=cid:inline-1.png>') !== false, 'html');
    });

    FsmtpTest::case('Elastic Email: an unsafe cid used only without quotes keeps the real name and the HTML', function () use ($elastic, $image) {
        $sent = $elastic('<img src=cid:abc@example.com>', function ($m) use ($image) { $image($m, 'abc@example.com', 'embed0'); });
        FsmtpTest::assertSame(['embed0'], $sent['names'], 'names');
        FsmtpTest::assert(strpos($sent['body'], '<img src=cid:abc@example.com>') !== false, 'html');
    });

    FsmtpTest::case('Elastic Email: an image that cannot be read keeps its cid in the HTML', function () use ($elastic, $png) {
        $path = wp_upload_dir()['basedir'] . '/' . FsmtpTest::uniq('fsmtp-gone') . '.png';
        file_put_contents($path, $png);
        $sent = $elastic('<img src="cid:abc@example.com">', function ($m) use ($path) {
            $m->addEmbeddedImage($path, 'abc@example.com', 'embed0', 'base64', 'image/png');
            unlink($path);
        });
        FsmtpTest::assertSame([], $sent['names'], 'names');
        FsmtpTest::assert(strpos($sent['body'], '<img src="cid:abc@example.com">') !== false, 'html');
    });

    FsmtpTest::case('Elastic Email: a cid is matched with its case, up to a closing slash', function () use ($elastic, $image) {
        $other = $elastic('<img src="cid:LOGO">', function ($m) use ($image) { $image($m, 'logo', 'a.png'); });
        FsmtpTest::assertSame(['a.png'], $other['names'], 'other case');

        $closed = $elastic('<img src=cid:logo/>', function ($m) use ($image) { $image($m, 'logo', 'a.png'); });
        FsmtpTest::assertSame(['logo'], $closed['names'], 'unquoted, self-closed');
    });

    FsmtpTest::case('Elastic Email: the bytes of an in-memory image are not read as a path', function () use ($elastic, $png) {
        $sent = $elastic('<img src="cid:logo">', function ($m) use ($png) { $m->addStringEmbeddedImage($png . '.exe', 'logo', 'a.png', 'base64', 'image/png'); });
        FsmtpTest::assertSame(['logo'], $sent['names'], 'names');
    });

    FsmtpTest::case('Elastic Email: only an image takes its cid as a name', function () use ($elastic, $image, $pdf, $png) {
        foreach ([
            'declared image, exe name' => ['x.exe', 'image/png'],
            'svg'                      => ['logo.svg', 'image/svg+xml'], // PHPMailer's type table has no svg, so it is not read as an image
            'dotted name'              => ['Logo v1.2', 'image/png'],
            'declared as a document'   => ['scan.png', 'application/pdf'],
        ] as $label => $file) {
            $sent = $elastic('<img src="cid:photo">', function ($m) use ($image, $file) { $image($m, 'photo', $file[0], $file[1]); });
            FsmtpTest::assertSame([$file[0]], $sent['names'], $label);
        }

        $inlinePdf = $elastic('<img src="cid:terms">', function ($m) use ($pdf) { $m->addStringEmbeddedImage($pdf, 'terms', 'terms.pdf', 'base64', 'application/pdf'); });
        FsmtpTest::assertSame(['terms.pdf'], $inlinePdf['names'], 'pdf');

        $path = wp_upload_dir()['basedir'] . '/' . FsmtpTest::uniq('fsmtp-prog') . '.exe';
        file_put_contents($path, $png);
        $sent = $elastic('<img src="cid:photo">', function ($m) use ($path) { $m->addEmbeddedImage($path, 'photo', 'report', 'base64', 'image/png'); });
        unlink($path);
        FsmtpTest::assertSame(['report'], $sent['names'], 'program on disk');
    });

    FsmtpTest::case('Elastic Email: a normal attachment beside an image keeps its name, even with an empty cid in the HTML', function () use ($elastic, $image, $png) {
        $sent = $elastic('<img src="cid:logo"><img src="cid:">', function ($m) use ($image, $png) {
            $m->addStringAttachment($png, 'chart.png', 'base64', 'image/png');
            $image($m, 'logo');
        });

        FsmtpTest::assertSame(['chart.png', 'logo'], $sent['names'], 'names');
    });

    FsmtpTest::case('Elastic Email: a plain-text email is left alone', function () use ($elastic, $image) {
        FsmtpTest::assertSame(['logo.png'], $elastic('<img src="cid:logo">', function ($m) use ($image) { $image($m, 'logo'); }, false)['names'], 'plain text');
    });
};
