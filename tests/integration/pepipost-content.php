<?php

use FluentMail\App\Services\Mailer\Providers\PepiPost\Handler as PepiPostHandler;

return function () {
    /*
     * PepiPost's (Netcore's) v5 API takes only `html` and `amp` content parts.
     * These cases pin what each kind of email sends, and that a send raises no
     * warning: the handler used to read PHPMailer::$contentType, which does not
     * exist, on every send.
     */
    FsmtpTest::requirePhpMailer();

    $send = function (callable $configure) {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom('sender@example.test', 'Suite');
        $mailer->addAddress('to@example.test');
        $mailer->Subject = 'pepipost content suite';
        $configure($mailer);

        $body = null;
        FsmtpTest::interceptHttp(function ($url, $args) use (&$body) {
            if (strpos($url, 'https://api.pepipost.com/') !== 0) {
                return null;
            }
            $body = json_decode($args['body'], true);
            return ['headers' => [], 'body' => '{"status":"success"}', 'response' => ['code' => 202, 'message' => 'Accepted'], 'cookies' => [], 'filename' => null];
        });

        $warnings = [];
        $previous = set_error_handler(function ($errno, $errstr, $errfile) use (&$warnings, &$previous) {
            if (strpos(str_replace('\\', '/', $errfile), 'Providers/PepiPost/') !== false) {
                $warnings[] = $errstr;
                return true;
            }
            return $previous ? call_user_func_array($previous, func_get_args()) : false;
        });
        add_filter('fluentmail_will_log_email', '__return_false', PHP_INT_MAX);
        try {
            (new PepiPostHandler())
                ->setSettings(['key_store' => 'db', 'api_key' => 'k', 'sender_email' => 'sender@example.test', 'sender_name' => '', 'force_from_name' => 'no', 'provider' => 'pepipost'])
                ->setPhpMailer($mailer)
                ->send();
        } finally {
            restore_error_handler();
            remove_filter('fluentmail_will_log_email', '__return_false', PHP_INT_MAX);
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assertSame([], $warnings, 'warnings raised by the PepiPost handler');
        FsmtpTest::assert(is_array($body), 'the request to PepiPost was not captured');

        return $body['content'];
    };

    FsmtpTest::case('PepiPost sends an HTML email as one html part, without a warning', function () use ($send) {
        $content = $send(function ($m) {
            $m->isHTML(true);
            $m->Body = '<p>Hello <b>there</b></p>';
        });
        FsmtpTest::assertSame([['type' => 'html', 'value' => '<p>Hello <b>there</b></p>']], $content, 'content');
    });

    FsmtpTest::case('PepiPost does not send a plain-text alternative as an AMP part', function () use ($send) {
        $content = $send(function ($m) {
            $m->isHTML(true);
            $m->Body = '<p>Hello</p>';
            $m->AltBody = 'Hello';
        });
        FsmtpTest::assertSame(['html'], array_column($content, 'type'), 'only an html part');
        FsmtpTest::assertSame('<p>Hello</p>', $content[0]['value'], 'the HTML body');
    });

    FsmtpTest::case('PepiPost sends a plain-text email as escaped HTML that keeps its line breaks', function () use ($send) {
        $content = $send(function ($m) {
            $m->isHTML(false);
            $m->Body = "Order #12 is \"ready\" &amp;\nTotal: 5 & 6 > 4\n\nThanks";
        });
        FsmtpTest::assertSame([[
            'type'  => 'html',
            'value' => "Order #12 is &quot;ready&quot; &amp;amp;<br>\nTotal: 5 &amp; 6 &gt; 4<br>\n<br>\nThanks",
        ]], $content, 'content');
    });

    FsmtpTest::case('PepiPost keeps the HTML of a multipart email the plugin built', function () use ($send) {
        foreach (['multipart/alternative', 'Multipart/Alternative'] as $type) {
            $content = $send(function ($m) use ($type) {
                // What the plugin's plain-text-alternative option sets (app/Functions/helpers.php).
                $m->Body = '<p>Hello <b>there</b></p>';
                $m->AltBody = 'Hello there';
                $m->ContentType = $type;
            });
            FsmtpTest::assertSame([['type' => 'html', 'value' => '<p>Hello <b>there</b></p>']], $content, $type);
        }

        // Tag-free HTML (an entity only) must not be escaped again because the type is multipart.
        $content = $send(function ($m) {
            $m->Body = 'Fish &amp; chips';
            $m->AltBody = 'Fish & chips';
            $m->ContentType = 'multipart/alternative';
        });
        FsmtpTest::assertSame('Fish &amp; chips', $content[0]['value'], 'tag-free multipart HTML');
    });

    FsmtpTest::case('PepiPost leaves HTML sent without an HTML content type unescaped, as before', function () use ($send) {
        $content = $send(function ($m) {
            $m->isHTML(false);
            $m->Body = '<p>Order <a href="https://example.test/o/12">#12</a> is ready</p>';
        });
        FsmtpTest::assertSame('<p>Order <a href="https://example.test/o/12">#12</a> is ready</p>', $content[0]['value'], 'unescaped');
    });
};
