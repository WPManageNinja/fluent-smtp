<?php

use FluentMail\App\Services\Mailer\Providers\Mailgun\Handler as MailgunHandler;

return function () {
    /*
     * Custom headers reach handlers through BaseHandler::getCustomHeaderList()
     * and are stored intact in the log row's extra.custom_headers, so a Resend
     * can replay them. Each email is built by the real wp_mail(), whose header
     * parsing is part of what is pinned, and the PHPMailer it hands to
     * phpmailer_init is then sent through a real handler with HTTP intercepted.
     */
    FsmtpTest::requirePhpMailer();

    // Real wp_mail() (simulated in the suite), returning a copy of the PHPMailer it built.
    $built = function (array $headers, ?callable $atInit = null) {
        $copy = null;
        $capture = function ($phpmailer) use (&$copy) {
            $copy = clone $phpmailer;
        };
        if ($atInit) {
            add_action('phpmailer_init', $atInit, 10);
        }
        add_action('phpmailer_init', $capture, PHP_INT_MAX);
        // The simulated send writes its own log row; the case only needs the PHPMailer.
        add_filter('fluentmail_will_log_email', '__return_false', PHP_INT_MAX);
        try {
            wp_mail('to@example.test', 'custom headers suite', 'body', $headers);
        } finally {
            remove_action('phpmailer_init', $capture, PHP_INT_MAX);
            remove_filter('fluentmail_will_log_email', '__return_false', PHP_INT_MAX);
            if ($atInit) {
                remove_action('phpmailer_init', $atInit, 10);
            }
        }
        FsmtpTest::assert($copy !== null, 'phpmailer_init did not fire');
        return $copy;
    };

    // Send through a handler with its API answered locally and the log on an isolated table.
    $send = function ($handler, $mailer, $apiUrl, array $answer, array $settings = [], $rowId = null) {
        global $wpdb;
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        if ($rowId) {
            $rowId = FsmtpFactory::insertLog($table, $rowId);
            $handler->setRowId($rowId);
        }

        $request = null;
        FsmtpTest::interceptHttp(function ($url, $args) use (&$request, $apiUrl, $answer) {
            if (strpos($url, $apiUrl) !== 0) {
                return null;
            }
            $request = $args['body'];
            return array_merge(['headers' => [], 'cookies' => [], 'filename' => null], $answer);
        });
        add_filter('query', $redirect, PHP_INT_MAX);
        add_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
        try {
            $handler
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
            remove_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
            FsmtpTest::interceptHttp();
        }

        FsmtpTest::assert($request !== null, 'the request to ' . $apiUrl . ' was not captured');
        $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);

        return ['request' => $request, 'rows' => $rows, 'handler' => $handler];
    };

    $mailgun = function ($mailer, $rowId = null) use ($send) {
        return $send(
            new MailgunHandler(),
            $mailer,
            'https://api.mailgun.net/',
            ['body' => wp_json_encode(['id' => 'suite', 'message' => 'Queued']), 'response' => ['code' => 200, 'message' => 'OK']],
            ['domain_name' => 'mg.example.test', 'region' => 'us', 'provider' => 'mailgun'],
            $rowId
        );
    };

    $brevo = function ($mailer) use ($send) {
        $sent = $send(
            new \FluentMail\App\Services\Mailer\Providers\SendInBlue\Handler(),
            $mailer,
            'https://api.brevo.com/',
            ['body' => wp_json_encode(['messageId' => 'suite']), 'response' => ['code' => 201, 'message' => 'Created']]
        );
        return json_decode($sent['request'], true);
    };

    $smtp2go = function ($mailer) use ($send) {
        $sent = $send(
            new \FluentMail\App\Services\Mailer\Providers\Smtp2Go\Handler(),
            $mailer,
            'https://api.smtp2go.com/',
            ['body' => wp_json_encode(['data' => ['succeeded' => 1, 'email_id' => 'suite']]), 'response' => ['code' => 200, 'message' => 'OK']]
        );
        return json_decode($sent['request'], true);
    };

    $headerList = function ($handler) {
        $method = new ReflectionMethod($handler, 'getCustomHeaderList');
        $method->setAccessible(true);
        return $method->invoke($handler);
    };

    $storedHeaders = function (array $row) {
        $extra = unserialize($row['extra'], ['allowed_classes' => false]);
        return is_array($extra) && array_key_exists('custom_headers', $extra) ? $extra['custom_headers'] : 'no custom_headers key';
    };

    $long = '<https://example.test/u?t=' . str_repeat('a', 1200) . '>, <mailto:u@example.test>';

    FsmtpTest::case('getCustomHeaderList() gives every custom header in order, with a phpmailer_init repeat and no Return-Path', function () use ($built, $mailgun, $headerList, $long) {
        $mailer = $built([
            'X-First: one',
            'Return-Path: bounce@example.test',
            'X-Utf8: Zoë Bücher 例え',
            'X-Empty:',
            'X-Long: ' . $long,
        ], function ($phpmailer) {
            $phpmailer->addCustomHeader('X-Repeat', 'first');
            $phpmailer->addCustomHeader('X-Repeat', 'second');
        });

        $sent = $mailgun($mailer);

        FsmtpTest::assertSame([
            ['key' => 'X-First', 'value' => 'one'],
            ['key' => 'X-Utf8', 'value' => 'Zoë Bücher 例え'],
            ['key' => 'X-Empty', 'value' => ''],
            ['key' => 'X-Long', 'value' => $long],
            ['key' => 'X-Repeat', 'value' => 'first'],
            ['key' => 'X-Repeat', 'value' => 'second'],
        ], $headerList($sent['handler']), 'custom header list');
    });

    $repeatAtInit = function ($phpmailer) {
        $phpmailer->addCustomHeader('X-Repeat', 'first');
        $phpmailer->addCustomHeader('X-Repeat', 'second');
    };

    $withHeaders = [
        'Reply-To: Help Desk <help@example.test>',
        'List-Unsubscribe: <https://example.test/?fluentcrm=1&route=unsubscribe&secure_hash=abc123>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'X-Utf8: Zoë Bücher 例え',
        'X-Empty:',
    ];

    FsmtpTest::case('Brevo sends every custom header in its headers object, without a second Reply-To', function () use ($built, $brevo, $withHeaders, $repeatAtInit) {
        $body = $brevo($built($withHeaders, function ($phpmailer) use ($repeatAtInit) {
            $repeatAtInit($phpmailer);
            // A listener can add Reply-To as a custom header; Brevo gets it once, in replyTo.
            // Lower case, as a listener may write it.
            $phpmailer->addCustomHeader('reply-to', 'listener@example.test');
        }));

        FsmtpTest::assertSame([
            'List-Unsubscribe'      => '<https://example.test/?fluentcrm=1&route=unsubscribe&secure_hash=abc123>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            'X-Utf8'                => 'Zoë Bücher 例え',
            'X-Empty'               => '',
            // A map keeps one value per name: the last.
            'X-Repeat'              => 'second',
        ], isset($body['headers']) ? $body['headers'] : null, 'Brevo headers');
        FsmtpTest::assertSame(['email' => 'help@example.test', 'name' => 'Help Desk'], $body['replyTo'], 'Brevo replyTo');
    });

    FsmtpTest::case('Brevo sends no headers key when there are no custom headers', function () use ($built, $brevo) {
        $body = $brevo($built(['Reply-To: help@example.test']));

        FsmtpTest::assertSame(false, array_key_exists('headers', $body), 'headers key present');
    });

    FsmtpTest::case('SMTP2GO sends Reply-To first, then every custom header with a value in order, repeats kept', function () use ($built, $smtp2go, $withHeaders, $repeatAtInit) {
        $body = $smtp2go($built($withHeaders, function ($phpmailer) use ($repeatAtInit) {
            $repeatAtInit($phpmailer);
            // Lower case, as a listener may write it.
            $phpmailer->addCustomHeader('reply-to', 'listener@example.test');
        }));

        FsmtpTest::assertSame([
            ['header' => 'Reply-To', 'value' => 'Help Desk <help@example.test>'],
            ['header' => 'List-Unsubscribe', 'value' => '<https://example.test/?fluentcrm=1&route=unsubscribe&secure_hash=abc123>'],
            ['header' => 'List-Unsubscribe-Post', 'value' => 'List-Unsubscribe=One-Click'],
            ['header' => 'X-Utf8', 'value' => 'Zoë Bücher 例え'],
            ['header' => 'X-Repeat', 'value' => 'first'],
            ['header' => 'X-Repeat', 'value' => 'second'],
        ], isset($body['custom_headers']) ? $body['custom_headers'] : null, 'SMTP2GO custom_headers');
    });

    FsmtpTest::case('SMTP2GO sends only Reply-To, or no custom_headers, when there are no custom headers', function () use ($built, $smtp2go) {
        $body = $smtp2go($built(['Reply-To: help@example.test']));
        FsmtpTest::assertSame([['header' => 'Reply-To', 'value' => 'help@example.test']], $body['custom_headers'], 'with Reply-To');

        $body = $smtp2go($built([]));
        FsmtpTest::assertSame(false, array_key_exists('custom_headers', $body), 'custom_headers key present without Reply-To');
    });

    FsmtpTest::case('a header value that is not valid UTF-8 still reaches Brevo and SMTP2GO, as wp_json_encode() writes it', function () use ($built, $brevo, $smtp2go) {
        // What WordPress makes of the bytes, the same way Postmark and Cloudflare already send them.
        $expected = json_decode(wp_json_encode("Ren\xE9e caf\xE9"));
        $mailer = $built(["X-Bad: Ren\xE9e caf\xE9", 'X-Good: plain']);

        $body = $brevo(clone $mailer);
        FsmtpTest::assert(is_array($body), 'Brevo request body is JSON');
        FsmtpTest::assertSame(['X-Bad' => $expected, 'X-Good' => 'plain'], $body['headers'], 'Brevo headers');

        $body = $smtp2go(clone $mailer);
        FsmtpTest::assert(is_array($body), 'SMTP2GO request body is JSON');
        FsmtpTest::assertSame([
            ['header' => 'X-Bad', 'value' => $expected],
            ['header' => 'X-Good', 'value' => 'plain'],
        ], $body['custom_headers'], 'SMTP2GO custom_headers');
    });

    FsmtpTest::case('the log row stores real senders\' header values byte for byte', function () use ($built, $mailgun, $storedHeaders, $long) {
        $values = [
            // FluentCRM
            'List-Unsubscribe'      => '<https://example.test/?fluentcrm=1&route=unsubscribe&secure_hash=abc123>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            // The Newsletter Plugin
            'X-Suite-Newsletter'    => '<mailto:unsubscribe@example.test?subject=unsubscribe>, <https://example.test/?na=u&nk=1-abc>',
            // Groundhogg
            'X-Suite-Groundhogg'    => '<https://example.test/gh/unsubscribe/?a=b%20c>',
            'References'            => '<a1@example.test> <b2@example.test>',
            'In-Reply-To'           => '<p1@example.test>',
            // Looks serialized: the sanitiser throws on it, which used to lose the row.
            'X-Suite-Serialized'    => 'N;',
            'X-Suite-Long'          => $long,
            'X-Suite-Utf8'          => 'Zoë Bücher 例え',
        ];
        $lines = [];
        $expected = [];
        foreach ($values as $name => $value) {
            $lines[] = $name . ': ' . $value;
            $expected[] = ['key' => $name, 'value' => $value];
        }

        $sent = $mailgun($built($lines));

        FsmtpTest::assertSame(1, count($sent['rows']), 'one log row');
        FsmtpTest::assertSame($expected, $storedHeaders($sent['rows'][0]), 'stored custom headers');
        $extra = unserialize($sent['rows'][0]['extra'], ['allowed_classes' => false]);
        FsmtpTest::assertSame('mailgun', $extra['provider'], 'the rest of extra is kept');
    });

    FsmtpTest::case('a header name or value that is not valid UTF-8 is stored as valid UTF-8, and the row is kept', function () use ($built, $mailgun, $storedHeaders) {
        $sent = $mailgun($built(["X-Suite-Bad: a\xE9b", "X-B\xE9d: v"]));

        FsmtpTest::assertSame(1, count($sent['rows']), 'one log row');
        $stored = $storedHeaders($sent['rows'][0]);
        // WordPress 6.9 and later put U+FFFD in place of the bad byte; earlier versions store an empty string.
        $replaced = version_compare(get_bloginfo('version'), '6.9', '>=');
        FsmtpTest::assertSame([
            ['key' => 'X-Suite-Bad', 'value' => $replaced ? "a\u{FFFD}b" : ''],
            ['key' => $replaced ? "X-B\u{FFFD}d" : '', 'value' => 'v'],
        ], $stored, 'stored headers');
    });

    FsmtpTest::case('an email without custom headers stores no custom_headers key', function () use ($built, $mailgun, $storedHeaders) {
        $sent = $mailgun($built([]));

        FsmtpTest::assertSame(1, count($sent['rows']), 'one log row');
        FsmtpTest::assertSame('no custom_headers key', $storedHeaders($sent['rows'][0]), 'no key');
    });

    FsmtpTest::case('a fallback send updates the existing row with its custom headers intact', function () use ($built, $mailgun, $storedHeaders) {
        $sent = $mailgun($built(['List-Unsubscribe: <https://example.test/u?h=1>', 'X-Probe: fallback']), [
            'status'  => 'failed',
            'extra'   => serialize(['provider' => 'smtp']),
            'retries' => 0,
        ]);

        FsmtpTest::assertSame(1, count($sent['rows']), 'still one log row');
        FsmtpTest::assertSame('1', (string) $sent['rows'][0]['retries'], 'the fallback branch ran');
        FsmtpTest::assertSame([
            ['key' => 'List-Unsubscribe', 'value' => '<https://example.test/u?h=1>'],
            ['key' => 'X-Probe', 'value' => 'fallback'],
        ], $storedHeaders($sent['rows'][0]), 'stored custom headers');
    });

    /*
     * The six handlers that already sent custom headers read them through
     * getCustomHeaderList() too. Their fields are pinned to what master sends
     * for the same email: Mailgun and Elastic Email trim and keep the last of
     * a repeat, ToSend and Cloudflare keep a map, Postmark keeps every entry,
     * and Outlook's Graph path keeps only non-empty x- headers.
     */
    FsmtpTest::case('Mailgun, Elastic Email, Postmark, ToSend, Cloudflare and Outlook send the same custom headers as before', function () use ($built, $send, $repeatAtInit) {
        $email = function () use ($built, $repeatAtInit) {
            return $built([
                'List-Unsubscribe: <https://example.test/?fluentcrm=1&route=unsubscribe&secure_hash=abc123>',
                'X-Utf8: Zoë Bücher 例え',
                'X-Empty:',
            ], $repeatAtInit);
        };
        $unsubscribe = '<https://example.test/?fluentcrm=1&route=unsubscribe&secure_hash=abc123>';
        $ok = ['body' => wp_json_encode(['id' => 'suite', 'message' => 'Queued', 'MessageID' => 'suite', 'success' => true, 'data' => ['messageid' => 'suite'], 'result' => ['message_id' => 'suite']]), 'response' => ['code' => 200, 'message' => 'OK']];

        $mailgun = $send(new MailgunHandler(), $email(), 'https://api.mailgun.net/', $ok, ['domain_name' => 'mg.example.test', 'region' => 'us']);
        $fields = [];
        foreach ($mailgun['request'] as $key => $value) {
            if (strpos($key, 'h:') === 0) {
                $fields[$key] = $value;
            }
        }
        FsmtpTest::assertSame([
            'h:X-Mailer'         => 'FluentMail - Mailgun',
            'h:List-Unsubscribe' => $unsubscribe,
            'h:X-Utf8'           => 'Zoë Bücher 例え',
            'h:X-Empty'          => '',
            'h:X-Repeat'         => 'second',
        ], $fields, 'Mailgun h: fields');

        $elastic = $send(new \FluentMail\App\Services\Mailer\Providers\ElasticMail\Handler(), $email(), 'https://api.elasticemail.com/', $ok);
        preg_match_all('/name=(headers_[^\r\n]+)\r\n\r\n([^\r\n]*)\r\n/', $elastic['request'], $parts, PREG_SET_ORDER);
        $fields = [];
        foreach ($parts as $part) {
            $fields[$part[1]] = $part[2];
        }
        FsmtpTest::assertSame([
            'headers_List-Unsubscribe' => 'List-Unsubscribe: ' . $unsubscribe,
            'headers_X-Utf8'           => 'X-Utf8: Zoë Bücher 例え',
            'headers_X-Empty'          => 'X-Empty: ',
            'headers_X-Repeat'         => 'X-Repeat: second',
        ], $fields, 'Elastic Email headers_ parts');

        $postmark = json_decode($send(new \FluentMail\App\Services\Mailer\Providers\Postmark\Handler(), $email(), 'https://api.postmarkapp.com/', $ok)['request'], true);
        FsmtpTest::assertSame([
            ['Name' => 'List-Unsubscribe', 'Value' => $unsubscribe],
            ['Name' => 'X-Utf8', 'Value' => 'Zoë Bücher 例え'],
            ['Name' => 'X-Empty', 'Value' => ''],
            ['Name' => 'X-Repeat', 'Value' => 'first'],
            ['Name' => 'X-Repeat', 'Value' => 'second'],
        ], $postmark['Headers'], 'Postmark Headers');

        $map = [
            'List-Unsubscribe' => $unsubscribe,
            'X-Utf8'           => 'Zoë Bücher 例え',
            'X-Empty'          => '',
            'X-Repeat'         => 'second',
        ];

        $cloudflare = json_decode($send(new \FluentMail\App\Services\Mailer\Providers\Cloudflare\Handler(), $email(), 'https://api.cloudflare.com/', $ok, ['account_id' => 'suiteaccount'])['request'], true);
        FsmtpTest::assertSame($map, $cloudflare['headers'], 'Cloudflare headers');

        // ToSend posts with curl, outside the WordPress HTTP API; only its transport is replaced.
        $toSend = new class extends \FluentMail\App\Services\Mailer\Providers\ToSend\Handler {
            public $sentBody = null;

            protected function sendViaCurl($url, $jsonBody)
            {
                $this->sentBody = $jsonBody;
                return ['code' => 200, 'body' => wp_json_encode(['message_id' => 'suite'])];
            }
        };
        $toSend
            ->setSettings(['key_store' => 'db', 'api_key' => 'suite-key', 'sender_email' => 'sender@example.test', 'sender_name' => '', 'force_from_name' => 'no'])
            ->setPhpMailer($email());
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            $toSend->send();
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
        }
        FsmtpTest::assert($toSend->sentBody !== null, 'ToSend request was not built');
        FsmtpTest::assertSame($map, json_decode($toSend->sentBody, true)['headers'], 'ToSend headers');

        $outlook = new \FluentMail\App\Services\Mailer\Providers\Outlook\Handler();
        $outlook->setSettings(['sender_email' => 'sender@example.test', 'sender_name' => '', 'force_from_name' => 'no'])->setPhpMailer($email());
        $preSend = new ReflectionMethod($outlook, 'preSend');
        $preSend->setAccessible(true);
        $preSend->invoke($outlook);
        $graph = new ReflectionMethod($outlook, 'graphInternetMessageHeaders');
        $graph->setAccessible(true);
        FsmtpTest::assertSame([
            ['name' => 'X-Utf8', 'value' => 'Zoë Bücher 例え'],
            ['name' => 'X-Repeat', 'value' => 'first'],
            ['name' => 'X-Repeat', 'value' => 'second'],
        ], $graph->invoke($outlook), 'Outlook Graph internetMessageHeaders');
    });
};
