<?php

use FluentMail\App\Services\Mailer\Providers\Smtp\Handler as SmtpHandler;
use FluentMail\App\Services\Mailer\Providers\Smtp\ServerReplies;

return function () {
    /*
     * A failed SMTP send carries the server's own reason after PHPMailer's
     * sentence: in the message wp_mail_failed and the alerts receive, and in
     * the log row. Each case starts tests/fixtures/fake-smtp.php, which
     * refuses at one stage, and sends through the real SMTP handler on the
     * global PHPMailer, the object a bulk session tracks.
     *
     * Named to sort before the resend tests: a resend defines
     * FLUENTMAIL_LOG_OFF, after which no log row is written in this process.
     */
    FsmtpTest::requirePhpMailer();
    $originalMailer = isset($GLOBALS['phpmailer']) ? $GLOBALS['phpmailer'] : null;
    // An earlier file may have loaded PHPMailer without its SMTP class (a send double that never connects).
    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';

    $start = function ($mode, $log = '', array $environment = []) {
        $command = [PHP_BINARY, dirname(__DIR__) . '/fixtures/fake-smtp.php', $mode];
        if ($log) {
            $command[] = $log;
        }
        $base = getenv();
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(is_array($base) ? $base : [], $environment));
        FsmtpTest::assert(is_resource($process), 'fake SMTP server did not start');
        $port = (int) trim((string) fgets($pipes[1]));
        if ($port < 1) {
            proc_terminate($process);
            FsmtpTest::fail('fake SMTP server reported no port: ' . stream_get_contents($pipes[2]));
        }
        return ['process' => $process, 'pipes' => $pipes, 'port' => $port];
    };

    $stop = function ($server) {
        proc_terminate($server['process']);
        foreach ($server['pipes'] as $pipe) {
            fclose($pipe);
        }
        proc_close($server['process']);
    };

    // A fresh global PHPMailer, as wp_mail() would leave it before a send.
    $freshMailer = function () {
        global $phpmailer;
        $phpmailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $phpmailer->Timeout = 10;
        return $phpmailer;
    };

    $smtpSettings = [
        'provider'         => 'smtp',
        'key_store'        => 'db',
        'host'             => '127.0.0.1',
        'port'             => 25,
        'auth'             => 'no',
        'username'         => '',
        'password'         => '',
        'encryption'       => 'none',
        'auto_tls'         => 'no',
        'sender_email'     => 'sender@example.test',
        'sender_name'      => '',
        'force_from_name'  => 'no',
        'force_from_email' => 'no',
        'return_path'      => 'no',
    ];

    $send = function (array $settings, $mailer = null, array $bcc = []) use ($freshMailer, $smtpSettings) {
        global $phpmailer, $wpdb;
        $mailer = $mailer ?: $freshMailer();
        $phpmailer = $mailer;
        $mailer->clearAllRecipients();
        $mailer->setFrom('sender@example.test', 'Suite Sender');
        $mailer->addAddress('to@example.test');
        foreach ($bcc as $address) {
            $mailer->addBCC($address);
        }
        $mailer->Subject = 'smtp error reason suite';
        $mailer->Body = 'body';
        $mailer->ContentType = 'text/plain';

        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        add_filter('query', $redirect, PHP_INT_MAX);
        add_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
        $message = null;
        try {
            (new SmtpHandler())
                ->setSettings(array_merge($smtpSettings, $settings))
                ->setPhpMailer($mailer)
                ->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $message = $e->getMessage();
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            remove_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
        }
        $row = $wpdb->get_row("SELECT status, response FROM `{$table}` ORDER BY id DESC LIMIT 1", ARRAY_A);
        FsmtpFactory::dropTable($table);

        return [
            'message'  => $message,
            'status'   => $row ? $row['status'] : null,
            'response' => $row ? maybe_unserialize($row['response']) : null,
            'smtp'     => $mailer->getSMTPInstance(),
        ];
    };

    // PHPMailer's own sentence differs between versions and languages, so cases read it from PHPMailer.
    $sentence = function ($key) {
        $translations = (new \PHPMailer\PHPMailer\PHPMailer())->getTranslations();
        return $translations[$key];
    };

    $refusals = [
        '421 in the greeting'        => ['greet', [], 'connect_host', '421 4.7.0 mail.example.test Too many connections from your IP, try again later'],
        '554 in the greeting'        => ['greet554', [], 'connect_host', '554 5.7.1 mail.example.test No SMTP service here'],
        '535 to the login'           => ['auth', ['auth' => 'yes', 'username' => 'probe-user', 'password' => 'wrong-password'], 'authenticate', '535 5.7.8 Username and Password not accepted. For more information, go to https://support.example.test/mail/?p=BadCredentials'],
        '554 after the message'      => ['data', [], 'data_not_accepted', '554 5.7.1 Message rejected: content looks like spam (score 9.2)'],
        'login asked, none offered'  => ['noauth', ['auth' => 'yes', 'username' => 'probe-user', 'password' => 'wrong-password'], 'authenticate', 'Authentication is not allowed at this stage'],
    ];
    foreach ($refusals as $label => $case) {
        FsmtpTest::case('a ' . $label . ' is logged with the server\'s reason after PHPMailer\'s sentence', function () use ($start, $stop, $send, $sentence, $case) {
            list($mode, $settings, $sentenceKey, $reason) = $case;
            $server = $start($mode);
            try {
                $result = $send(array_merge(['port' => $server['port']], $settings));
            } finally {
                $stop($server);
            }
            $expected = $sentence($sentenceKey) . ' ' . $reason;
            FsmtpTest::assertSame($expected, $result['message'], 'thrown message');
            FsmtpTest::assertSame('failed', $result['status'], 'log status');
            FsmtpTest::assertSame($expected, $result['response']['message'], 'logged message');
        });
    }

    // Unusual replies after the message, where PHPMailer's own sentence carries none of the server's text.
    $unusual = [
        // RFC 5321 allows 512 characters per reply line, CRLF included; PHPMailer reads that much per line.
        'a 500-character reply' => [['554 5.7.1 ' . str_repeat('x', 490)], '554 5.7.1 ' . str_repeat('x', 490)],
        'a reply line longer than RFC 5321 allows (PHPMailer reads the first part)' => [['554 5.7.1 ' . str_repeat('y', 4000)], '554 5.7.1 ' . str_repeat('y', 400)],
        'a 30-line reply' => [array_merge(array_map(function ($n) {
            return '554-5.7.1 policy line ' . $n;
        }, range(1, 29)), ['554 5.7.1 last line']), '554 5.7.1 policy line 1 policy line 2'],
        'a reply in UTF-8' => [['554 5.7.1 Nachricht abgelehnt: Größe überschritten 例え'], '554 5.7.1 Nachricht abgelehnt: Größe überschritten 例え'],
    ];
    foreach ($unusual as $label => $case) {
        FsmtpTest::case('a refusal with ' . $label . ' is logged with it', function () use ($start, $stop, $send, $sentence, $case) {
            list($lines, $expected) = $case;
            $server = $start('custom', '', ['FAKE_SMTP_REPLY' => base64_encode(implode("\n", $lines))]);
            try {
                $result = $send(['port' => $server['port']]);
            } finally {
                $stop($server);
            }
            FsmtpTest::assert(0 === strpos($result['message'], $sentence('data_not_accepted') . ' ' . $expected), 'thrown: ' . substr($result['message'], 0, 300));
            FsmtpTest::assertSame(false, strpos($result['message'], "\n"), 'one line');
            FsmtpTest::assertSame('failed', $result['status'], 'log status');
            FsmtpTest::assertSame($result['message'], $result['response']['message'], 'logged as thrown');
        });
    }

    // Postfix 3.7 replies at stages the cases above do not reach (smtpd.c, smtpd_sasl_glue.c, cleanup_strerror.c);
    // the content filter's wording is fictional, since a milter sends its own. The last field is what the message
    // must hold once: the whole reply, except where PHPMailer's own message already carries the server's text
    // without its code (STARTTLS), which is then left as it is.
    $stages = [
        'a 454 to STARTTLS'                                 => ['starttls', false, ['encryption' => 'tls'], 'connect_host', '454 4.7.0 TLS not available due to local problem', 'STARTTLS command failed TLS not available due to local problem'],
        'a 454 to the login'                                => ['auth', false, ['auth' => 'yes', 'username' => 'u', 'password' => 'p'], 'authenticate', '454 4.7.0 Temporary authentication failure: generic failure'],
        'a 421 to the login that closes the connection'     => ['auth', true, ['auth' => 'yes', 'username' => 'u', 'password' => 'p'], 'authenticate', '421 4.3.0 mail.example.test Server local error'],
        'a 451 after the message'                           => ['eod', false, [], 'data_not_accepted', '451 4.3.0 Error: queue file write error'],
        'a content filter\'s 421 after the message that closes the connection' => ['eod', true, [], 'data_not_accepted', '421 4.7.0 mail.example.test Content filter unavailable, try again later'],
        'a 554 greeting without an enhanced code'           => ['greeting', false, [], 'connect_host', '554 mail.example.test ESMTP not accepting connections'],
        'a 421 greeting without an enhanced code'           => ['greeting', true, [], 'connect_host', '421 mail.example.test Service unavailable - try again later'],
    ];
    foreach ($stages as $label => $case) {
        FsmtpTest::case($label . ' is logged with the server\'s reply, once', function () use ($start, $stop, $send, $sentence, $case) {
            list($at, $close, $settings, $sentenceKey, $reply) = $case;
            $held = isset($case[5]) ? $case[5] : $reply;
            $server = $start('custom', '', ['FAKE_SMTP_REPLY' => base64_encode($reply), 'FAKE_SMTP_AT' => $at, 'FAKE_SMTP_CLOSE' => $close ? '1' : '0']);
            try {
                $result = $send(array_merge(['port' => $server['port']], $settings));
            } finally {
                $stop($server);
            }
            FsmtpTest::assert(0 === strpos((string) $result['message'], $sentence($sentenceKey)), 'PHPMailer\'s sentence first: ' . $result['message']);
            FsmtpTest::assertSame(1, substr_count((string) $result['message'], $held), 'the reply once in: ' . $result['message']);
            // The log trims the line break PHPMailer ends its STARTTLS message with.
            FsmtpTest::assertSame(trim((string) $result['message']), $result['response']['message'], 'logged as thrown');
        });
    }

    // With no reply at all there is no server reason, so nothing may read as one.
    foreach (['auth' => [['auth' => 'yes', 'username' => 'u', 'password' => 'p'], 'authenticate'], 'eod' => [[], 'data_not_accepted']] as $at => $case) {
        FsmtpTest::case('a connection closed without a reply at ' . $at . ' is not logged with a reply code', function () use ($start, $stop, $send, $sentence, $at, $case) {
            list($settings, $sentenceKey) = $case;
            $server = $start('custom', '', ['FAKE_SMTP_REPLY' => '', 'FAKE_SMTP_AT' => $at, 'FAKE_SMTP_CLOSE' => '1']);
            try {
                $result = $send(array_merge(['port' => $server['port']], $settings));
            } finally {
                $stop($server);
            }
            $after = substr((string) $result['message'], strlen($sentence($sentenceKey)));
            FsmtpTest::assert(0 === strpos((string) $result['message'], $sentence($sentenceKey)), 'PHPMailer\'s sentence first: ' . $result['message']);
            FsmtpTest::assertSame(0, preg_match('/\b[2-5]\d\d\b/', $after), 'no reply code after the sentence: ' . $result['message']);
        });
    }

    FsmtpTest::case('a refusal that is not valid UTF-8 keeps PHPMailer\'s sentence alone and raises no notice', function () use ($start, $stop, $send, $sentence) {
        $server = $start('custom', '', ['FAKE_SMTP_REPLY' => base64_encode("554 5.7.1 Nachricht abgelehnt: Gr\xF6\xDFe")]);
        try {
            $result = $send(['port' => $server['port']]);
        } finally {
            $stop($server);
        }
        FsmtpTest::assertSame($sentence('data_not_accepted'), $result['message'], 'thrown message');
        FsmtpTest::assertSame('failed', $result['status'], 'log status');
    });

    FsmtpTest::case('550, 553 and a refused STARTTLS keep PHPMailer\'s message, which already holds the server\'s text, with nothing appended', function () use ($start, $stop, $send) {
        foreach (['rcpt' => '550 5.1.1 <to@example.test>', 'sender' => '553 5.7.1 <sender@example.test>', 'starttls-refused' => '502 5.5.2'] as $mode => $appended) {
            if ('starttls-refused' === $mode) {
                $server = $start('ok');
                try {
                    $result = $send(['port' => $server['port'], 'encryption' => 'tls']);
                } finally {
                    $stop($server);
                }
                FsmtpTest::assertSame(false, strpos($result['message'], $appended), $mode . ': the reply was appended again to ' . $result['message']);
                FsmtpTest::assert(false !== strpos($result['message'], 'command not recognized'), $mode . ': the server text is in the message');
                continue;
            }
            $server = $start($mode);
            try {
                $result = $send(['port' => $server['port']]);
            } finally {
                $stop($server);
            }
            FsmtpTest::assertSame(false, strpos($result['message'], $appended), $mode . ': the reply was appended again to ' . $result['message']);
            FsmtpTest::assert(false !== strpos($result['message'], 'address rejected'), $mode . ': the server text is in the message');
        }
    });

    /*
     * The log's sanitize_text_field() reads <...> as an HTML tag and erases it, which took the address out of
     * every Postfix refusal. In an SMTP reply the brackets are protocol syntax, so the log keeps what is inside;
     * the thrown message (wp_mail_failed, Send Test Email) keeps the reply as the server sent it. Shapes from
     * Postfix 3.7 (smtpd_check.c: sender, recipient, HELO, client) and Courier (a refused local part).
     */
    $bracketed = [
        'a 550 recipient refusal'      => ['rcpt', [], '<to@example.test>: Recipient address rejected', 'to@example.test: Recipient address rejected'],
        'a 553 sender refusal'         => ['sender', [], '<sender@example.test>: Sender address rejected', 'sender@example.test: Sender address rejected'],
        'a HELO name refusal'          => ['custom', ['FAKE_SMTP_AT' => 'rcpt', 'FAKE_SMTP_REPLY' => base64_encode('554 5.7.1 <site.example.org>: Helo command rejected: Host not found')], '<site.example.org>: Helo command rejected', 'site.example.org: Helo command rejected'],
        'a client host refusal'        => ['custom', ['FAKE_SMTP_AT' => 'rcpt', 'FAKE_SMTP_REPLY' => base64_encode('554 5.7.1 <client.example.net[192.0.2.4]>: Client host rejected: Access denied')], '<client.example.net[192.0.2.4]>: Client host', 'client.example.net[192.0.2.4]: Client host rejected'],
        'a refused local part'         => ['custom', ['FAKE_SMTP_AT' => 'rcpt', 'FAKE_SMTP_REPLY' => base64_encode('550 <nobody>: User unknown')], '<nobody>: User unknown', 'nobody: User unknown'],
        'a punycode domain refusal'    => ['custom', ['FAKE_SMTP_AT' => 'rcpt', 'FAKE_SMTP_REPLY' => base64_encode('550 5.1.1 <info@xn--bcher-kva.example>: Recipient address rejected')], '<info@xn--bcher-kva.example>: Recipient', 'info@xn--bcher-kva.example: Recipient address rejected'],
        'a UTF-8 domain refusal'       => ['custom', ['FAKE_SMTP_AT' => 'rcpt', 'FAKE_SMTP_REPLY' => base64_encode('550 5.1.1 <info@bücher.example>: Recipient address rejected')], '<info@bücher.example>: Recipient', 'info@bücher.example: Recipient address rejected'],
    ];
    foreach ($bracketed as $label => $case) {
        FsmtpTest::case($label . ' keeps what the server put in angle brackets in the log', function () use ($start, $stop, $send, $case) {
            list($mode, $environment, $thrown, $logged) = $case;
            $server = $start($mode, '', $environment);
            try {
                $result = $send(['port' => $server['port']]);
            } finally {
                $stop($server);
            }
            FsmtpTest::assert(false !== strpos((string) $result['message'], $thrown), 'thrown as the server sent it: ' . $result['message']);
            FsmtpTest::assertSame('failed', $result['status'], 'log status');
            FsmtpTest::assert(false !== strpos((string) $result['response']['message'], $logged), 'logged with the inner text: ' . $result['response']['message']);
        });
    }

    FsmtpTest::case('a reply with markup in angle brackets still has the markup stripped from the log', function () use ($start, $stop, $send) {
        $server = $start('custom', '', ['FAKE_SMTP_AT' => 'rcpt', 'FAKE_SMTP_REPLY' => base64_encode('550 5.7.1 <img src=x onerror=alert(1)> <svg/onload=alert(1)> refused')]);
        try {
            $result = $send(['port' => $server['port']]);
        } finally {
            $stop($server);
        }
        FsmtpTest::assert(false !== strpos((string) $result['message'], '<img src=x onerror=alert(1)>'), 'thrown as the server sent it: ' . $result['message']);
        $logged = (string) $result['response']['message'];
        // A bracket with a space inside is left to the sanitiser, as before.
        FsmtpTest::assertSame(false, strpos($logged, 'onerror'), 'tag with attributes stripped: ' . $logged);
        // One without becomes plain text, with no bracket left to read as a tag.
        FsmtpTest::assert(false !== strpos($logged, 'svg/onload=alert(1) refused'), 'unspaced token kept as text: ' . $logged);
    });

    // A pin, not a catch: the null sender <> has nothing inside, so removing its brackets logs it as before.
    FsmtpTest::case('a refusal of the null sender <> is logged as before', function () use ($start, $stop, $send) {
        $server = $start('custom', '', ['FAKE_SMTP_AT' => 'mail', 'FAKE_SMTP_REPLY' => base64_encode('553 5.1.8 <>: Sender address rejected: Domain not found')]);
        try {
            $result = $send(['port' => $server['port']]);
        } finally {
            $stop($server);
        }
        FsmtpTest::assert(false !== strpos((string) $result['message'], '<>: Sender address rejected'), 'thrown as the server sent it: ' . $result['message']);
        FsmtpTest::assert(false !== strpos((string) $result['response']['message'], ': Sender address rejected: Domain not found'), 'logged: ' . $result['response']['message']);
    });

    /*
     * The fallback as FluentPHPMailer::sendViaFallback() runs it once it has its driver: inside the primary's
     * failed-send filter, on the same PHPMailer, with the primary's log row. The suite's Simulator would answer
     * the driver lookup itself, so the case builds the SMTP driver.
     */
    FsmtpTest::case('a failing SMTP fallback keeps what both servers put in angle brackets in the log', function () use ($start, $stop, $send, $smtpSettings) {
        $primary = $start('sender');
        $fallback = $start('rcpt');
        $runFallback = function ($status, $logId, $handler) use ($fallback, $smtpSettings) {
            $driver = (new SmtpHandler())->setSettings(array_merge($smtpSettings, ['port' => $fallback['port']]));
            $driver->setRowId($logId);
            return $driver->setPhpMailer($handler->getPhpMailer())->send();
        };
        add_filter('fluentmail_email_sending_failed', $runFallback, 9, 3);
        try {
            $result = $send(['port' => $primary['port']]);
        } finally {
            remove_filter('fluentmail_email_sending_failed', $runFallback, 9);
            $stop($primary);
            $stop($fallback);
        }
        // processResponse() catches what the fallback throws inside the filter, so the primary's refusal is thrown.
        FsmtpTest::assert(false !== strpos((string) $result['message'], '<sender@example.test>: Sender address rejected'), 'the primary\'s refusal thrown as sent: ' . $result['message']);
        FsmtpTest::assertSame('failed', $result['status'], 'log status');
        FsmtpTest::assert(false !== strpos((string) $result['response']['message'], 'sender@example.test: Sender address rejected'), 'primary logged: ' . $result['response']['message']);
        $fallbackMessage = isset($result['response']['fallback_response']['message']) ? (string) $result['response']['fallback_response']['message'] : '';
        FsmtpTest::assert(false !== strpos($fallbackMessage, 'to@example.test: Recipient address rejected'), 'fallback logged: ' . $fallbackMessage);
    });

    FsmtpTest::case('a failed connection is logged with the network error', function () use ($send, $start, $stop, $sentence) {
        // A port nothing listens on: start a server to learn a free port, then stop it.
        $server = $start('ok');
        $port = $server['port'];
        $stop($server);
        $result = $send(['port' => $port]);
        FsmtpTest::assertSame($sentence('connect_host') . ' Failed to connect to server Connection refused', $result['message'], 'closed port');

        $result = $send(['host' => 'smtp.fluentsmtp-suite.invalid', 'port' => 587]);
        FsmtpTest::assert(
            0 === strpos($result['message'], $sentence('connect_host') . ' Failed to connect to server php_network_getaddresses: getaddrinfo'),
            'unresolvable host: ' . $result['message']
        );
    });

    FsmtpTest::case('a TLS failure while connecting is logged with PHP\'s OpenSSL message', function () use ($start, $stop, $send) {
        $server = $start('ok');
        try {
            $result = $send(['port' => $server['port'], 'encryption' => 'ssl']);
        } finally {
            $stop($server);
        }
        FsmtpTest::assert(false !== strpos($result['message'], 'SSL operation failed'), 'SSL on a plain port: ' . $result['message']);

        $server = $start('ssl');
        try {
            $result = $send(['port' => $server['port'], 'encryption' => 'ssl']);
        } finally {
            $stop($server);
        }
        FsmtpTest::assert(false !== strpos($result['message'], 'certificate verify failed'), 'self-signed certificate on implicit SSL: ' . $result['message']);

        // On STARTTLS PHPMailer's own message already carries the OpenSSL text; this pins that it stays.
        $server = $start('starttls');
        try {
            $result = $send(['port' => $server['port'], 'encryption' => 'tls']);
        } finally {
            $stop($server);
        }
        FsmtpTest::assert(false !== strpos($result['message'], 'certificate verify failed'), 'self-signed certificate on STARTTLS: ' . $result['message']);
    });

    FsmtpTest::case('with several hosts, the last host\'s connection failure is reported', function () use ($start, $stop, $send) {
        $closed = $start('ok');
        $closedPort = $closed['port'];
        $stop($closed);
        $plain = $start('ok');
        try {
            // PHPMailer tries each "host:port" in turn: the first refuses, the second cannot do SSL.
            $result = $send(['host' => '127.0.0.1:' . $closedPort . ';127.0.0.1:' . $plain['port'], 'encryption' => 'ssl']);
        } finally {
            $stop($plain);
        }
        FsmtpTest::assert(false !== strpos($result['message'], 'SSL operation failed'), 'second host\'s failure: ' . $result['message']);
        FsmtpTest::assertSame(false, strpos($result['message'], 'Connection refused'), 'the first host\'s refusal is not the reason given');
    });

    FsmtpTest::case('the password never reaches the message, the log or the recorded replies', function () use ($start, $stop, $send, $sentence) {
        $username = 'suite-user@example.test';
        $password = 'S3cret-suite-pass';
        $forbidden = [$password, base64_encode($username), base64_encode($password), base64_encode("\0" . $username . "\0" . $password)];

        // echo answers AUTH LOGIN (the password line); plainecho offers only AUTH PLAIN (the whole login in one line).
        foreach (['auth', 'echo', 'plainecho'] as $mode) {
            $server = $start($mode);
            try {
                $result = $send(['port' => $server['port'], 'auth' => 'yes', 'username' => $username, 'password' => $password]);
            } finally {
                $stop($server);
            }
            // What leaves the send: the thrown message (wp_mail_failed, alerts) and the log row.
            $stored = serialize([$result['message'], $result['response']]);
            if ('auth' === $mode) {
                // A server that does not echo: our recorded lists hold no login either.
                foreach (['refusals', 'connectionWarnings'] as $property) {
                    $reflection = new \ReflectionProperty(ServerReplies::class, $property);
                    if (PHP_VERSION_ID < 80100) {
                        // Needed before PHP 8.1, deprecated in 8.5.
                        $reflection->setAccessible(true);
                    }
                    $stored .= serialize($reflection->getValue($result['smtp']));
                }
            }
            foreach ($forbidden as $secret) {
                FsmtpTest::assertSame(false, strpos($stored, $secret), $mode . ': a login value was recorded');
            }
            if ('auth' === $mode) {
                FsmtpTest::assert(false !== strpos($result['message'], '535 5.7.8 Username and Password not accepted'), 'the reason is there: ' . $result['message']);
            } else {
                FsmtpTest::assertSame($sentence('authenticate'), $result['message'], $mode . ': an echoing reply is left out');
            }
        }
    });

    // The log keeps what is inside angle brackets, so a login echoed inside them must still be left out.
    FsmtpTest::case('a login echoed inside angle brackets is left out of the message and the log', function () use ($start, $stop, $send, $sentence) {
        $password = 'S3cret-suite-pass';
        $server = $start('custom', '', ['FAKE_SMTP_AT' => 'auth', 'FAKE_SMTP_REPLY' => base64_encode('535 5.7.8 Authentication failed for <' . base64_encode($password) . '>')]);
        try {
            $result = $send(['port' => $server['port'], 'auth' => 'yes', 'username' => 'suite-user@example.test', 'password' => $password]);
        } finally {
            $stop($server);
        }
        FsmtpTest::assertSame($sentence('authenticate'), $result['message'], 'the echoing reply is left out');
        FsmtpTest::assertSame('failed', $result['status'], 'log status');
        $stored = serialize($result['response']);
        FsmtpTest::assertSame(false, strpos($stored, base64_encode($password)), 'the login is not logged: ' . $stored);
    });

    FsmtpTest::case('a later failure does not carry an earlier send\'s reason', function () use ($start, $stop, $send, $freshMailer, $sentence) {
        $mailer = $freshMailer();
        $server = $start('greet');
        try {
            $first = $send(['port' => $server['port']], $mailer);
        } finally {
            $stop($server);
        }
        FsmtpTest::assert(false !== strpos($first['message'], '421'), 'first send fails with 421: ' . $first['message']);

        // PHPMailer rejects this host before connecting, so nothing new is said by any server.
        $second = $send(['host' => 'bad host!', 'port' => 25], $mailer);
        FsmtpTest::assertSame($sentence('connect_host'), $second['message'], 'second message');
    });

    FsmtpTest::case('a bulk session keeps one connection with RSET between emails', function () use ($start, $stop, $send, $freshMailer) {
        $log = wp_tempnam('fake-smtp-log');
        $server = $start('ok', $log);
        $mailer = $freshMailer();
        do_action('fluent_crm/email_sender_session_started');
        try {
            for ($i = 1; $i <= 3; $i++) {
                $result = $send(['port' => $server['port']], $mailer);
                FsmtpTest::assertSame(null, $result['message'], 'send ' . $i . ' failed');
                FsmtpTest::assertSame('sent', $result['status'], 'send ' . $i . ' log status');
            }
            FsmtpTest::assert($mailer->getSMTPInstance() instanceof ServerReplies, 'the recording instance is in place');
        } finally {
            do_action('fluent_crm/email_sender_session_ended');
            $stop($server);
        }
        $commands = array_values(array_filter(array_map('trim', file($log))));
        unlink($log);
        FsmtpTest::assertSame(1, count(array_filter($commands, function ($c) { return 0 === stripos($c, 'EHLO'); })), 'one connection for three emails');
        FsmtpTest::assertSame(3, count(array_filter($commands, function ($c) { return 'RSET' === $c; })), 'RSET after each email');
    });

    FsmtpTest::case('a refusal inside a bulk session is logged with the server\'s reason', function () use ($start, $stop, $send, $freshMailer) {
        $server = $start('data');
        $mailer = $freshMailer();
        do_action('fluent_crm/email_sender_session_started');
        try {
            $result = $send(['port' => $server['port']], $mailer);
        } finally {
            do_action('fluent_crm/email_sender_session_ended');
            $stop($server);
        }
        FsmtpTest::assert(false !== strpos($result['response']['message'], '554 5.7.1 Message rejected: content looks like spam'), 'logged: ' . $result['response']['message']);
    });

    FsmtpTest::case('when a bulk session\'s connection dies and the retry cannot connect, the retry\'s own reason is logged', function () use ($start, $stop, $send, $freshMailer) {
        $server = $start('ok');
        $port = $server['port'];
        $mailer = $freshMailer();
        $stopped = false;
        do_action('fluent_crm/email_sender_session_started');
        try {
            $first = $send(['port' => $port], $mailer);
            FsmtpTest::assertSame('sent', $first['status'], 'first send');
            // The kept-open connection dies with the server; nothing listens on the port any more.
            $stop($server);
            $stopped = true;
            $second = $send(['port' => $port], $mailer);
        } finally {
            do_action('fluent_crm/email_sender_session_ended');
            if (!$stopped) {
                $stop($server);
            }
        }
        FsmtpTest::assertSame('failed', $second['status'], 'second send');
        FsmtpTest::assert(
            false !== strpos($second['response']['message'], 'Connection refused'),
            'the retry\'s connection error: ' . $second['response']['message']
        );
    });

    FsmtpTest::case('when the server refuses on a kept-open connection and the retry meets a busy server, the retry\'s reason is logged', function () use ($start, $stop, $send, $freshMailer) {
        $server = $start('busy');
        $mailer = $freshMailer();
        do_action('fluent_crm/email_sender_session_started');
        try {
            $first = $send(['port' => $server['port']], $mailer);
            FsmtpTest::assertSame('sent', $first['status'], 'first send');
            // 550 on the open connection; the retry reconnects and is greeted with 421.
            $second = $send(['port' => $server['port']], $mailer);
        } finally {
            do_action('fluent_crm/email_sender_session_ended');
            $stop($server);
        }
        FsmtpTest::assert(false !== strpos($second['response']['message'], '421 4.7.0'), 'the retry\'s reason: ' . $second['response']['message']);
        FsmtpTest::assertSame(false, strpos($second['response']['message'], '550 5.1.1'), 'the first attempt\'s 550 is not reported for the retry');
    });

    FsmtpTest::case('when the bulk retry is refused with angle brackets, the retry\'s reply is logged with what was inside them', function () use ($start, $stop, $send, $freshMailer) {
        $log = wp_tempnam('fake-smtp-log');
        $commands = [];
        $server = $start('busy', $log, ['FAKE_SMTP_REPLY' => base64_encode('554 5.7.1 <client.example.net[192.0.2.4]>: Client host rejected: Access denied')]);
        $mailer = $freshMailer();
        do_action('fluent_crm/email_sender_session_started');
        try {
            $first = $send(['port' => $server['port']], $mailer);
            FsmtpTest::assertSame('sent', $first['status'], 'first send');
            // 550 on the open connection; the retry reconnects and its recipient is refused.
            $second = $send(['port' => $server['port']], $mailer);
        } finally {
            do_action('fluent_crm/email_sender_session_ended');
            $stop($server);
            $commands = array_values(array_filter(array_map('trim', (array) file($log))));
            unlink($log);
        }
        FsmtpTest::assertSame(2, count(array_filter($commands, function ($c) { return 0 === stripos($c, 'EHLO'); })), 'the retry reconnected');
        FsmtpTest::assert(false !== strpos((string) $second['message'], '<client.example.net[192.0.2.4]>: Client host rejected'), 'thrown as the server sent it: ' . $second['message']);
        FsmtpTest::assertSame('failed', $second['status'], 'second send');
        $logged = (string) $second['response']['message'];
        FsmtpTest::assert(false !== strpos($logged, 'client.example.net[192.0.2.4]: Client host rejected'), 'the retry\'s reply logged with the inner text: ' . $logged);
        FsmtpTest::assertSame(false, strpos($logged, 'User unknown'), 'the first attempt\'s 550 is not reported for the retry');
    });

    FsmtpTest::case('when the server closes an idle bulk connection with 421 and the retry cannot connect, the retry\'s reason is logged, not the 421', function () use ($start, $stop, $send, $freshMailer) {
        $server = $start('idle');
        $mailer = $freshMailer();
        do_action('fluent_crm/email_sender_session_started');
        try {
            $first = $send(['port' => $server['port']], $mailer);
            FsmtpTest::assertSame('sent', $first['status'], 'first send');
            $second = $send(['port' => $server['port']], $mailer);
        } finally {
            do_action('fluent_crm/email_sender_session_ended');
            $stop($server);
        }
        FsmtpTest::assertSame('failed', $second['status'], 'second send');
        FsmtpTest::assert(false !== strpos($second['response']['message'], 'Connection refused'), 'the retry\'s reason: ' . $second['response']['message']);
        FsmtpTest::assertSame(false, strpos($second['response']['message'], '4.4.2'), 'the earlier 421 is not reported for the retry');
    });

    FsmtpTest::case('when a recipient is refused and then the message, the message\'s refusal is logged', function () use ($start, $stop, $send, $sentence) {
        $server = $start('mixed');
        try {
            $result = $send(['port' => $server['port']], null, ['bad@example.test']);
        } finally {
            $stop($server);
        }
        FsmtpTest::assertSame($sentence('data_not_accepted') . ' 554 5.7.1 Message rejected: content looks like spam (score 9.2)', $result['message'], 'thrown message');
    });

    FsmtpTest::case('a server that refuses too many recipients and hangs up is logged with its closing reply, not with the recipients PHPMailer tries after it', function () use ($start, $stop, $send, $sentence) {
        $server = $start('drop');
        try {
            $result = $send(['port' => $server['port']], null, ['bad1@example.test', 'bad2@example.test', 'bad3@example.test', 'bad4@example.test', 'bad5@example.test', 'bad6@example.test', 'bad7@example.test', 'bad8@example.test']);
        } finally {
            $stop($server);
        }
        FsmtpTest::assertSame($sentence('data_not_accepted') . ' 421 4.7.0 mail.example.test Error: too many errors', $result['message'], 'thrown message');
    });

    FsmtpTest::case('a server that closes the connection without replying is logged with PHPMailer\'s sentence alone, not with a recipient refusal it never sent', function () use ($start, $stop, $send, $sentence) {
        $server = $start('hangup');
        try {
            $result = $send(['port' => $server['port']], null, ['bad1@example.test', 'bad2@example.test', 'bad3@example.test']);
        } finally {
            $stop($server);
        }
        FsmtpTest::assertSame($sentence('data_not_accepted'), $result['message'], 'thrown message');
        FsmtpTest::assertSame($sentence('data_not_accepted'), $result['response']['message'], 'logged message');
    });

    FsmtpTest::case('another plugin\'s SMTP class is left in place and the message stays PHPMailer\'s', function () use ($start, $stop, $send, $freshMailer, $sentence) {
        $mailer = $freshMailer();
        $own = new class extends \PHPMailer\PHPMailer\SMTP {
        };
        $mailer->setSMTPInstance($own);
        $server = $start('auth');
        try {
            $result = $send(['port' => $server['port'], 'auth' => 'yes', 'username' => 'u', 'password' => 'p'], $mailer);
        } finally {
            $stop($server);
        }
        FsmtpTest::assertSame($own, $mailer->getSMTPInstance(), 'SMTP instance');
        FsmtpTest::assertSame($sentence('authenticate'), $result['message'], 'message');
    });

    FsmtpTest::case('a plugin that turns keep-alive on in phpmailer_init still gets one connection per email, so each email is logged with its own server\'s reason', function () use ($start, $stop, $send, $freshMailer, $sentence) {
        // Contact Form by BestWebSoft 4.3.9 (contact_form.php:2209), All-in-One Forms 1.3.43, Newsletters
        // Lite 4.18.2 and Croatian Payment Slip 1.7.1 set SMTPKeepAlive in phpmailer_init, which wp_mail()
        // runs before the handler (helpers.php phpmailer_init, then FluentPHPMailer::send()). The recording
        // relies on only a bulk session keeping a connection open. Mode busy answers a second email on the
        // same connection with 550 and a new connection with 421, so the second message tells them apart.
        $log = wp_tempnam('fake-smtp-log');
        $server = $start('busy', $log);
        $mailer = $freshMailer();
        try {
            $mailer->SMTPKeepAlive = true;
            $first = $send(['port' => $server['port']], $mailer);
            FsmtpTest::assertSame('sent', $first['status'], 'first send');
            FsmtpTest::assertSame(false, $mailer->SMTPKeepAlive, 'keep-alive is off outside a bulk session');
            $mailer->SMTPKeepAlive = true;
            $second = $send(['port' => $server['port']], $mailer);
        } finally {
            $stop($server);
            $commands = array_values(array_filter(array_map('trim', (array) file($log))));
            unlink($log);
        }
        // The second connection is greeted with 421 and closed before the client says anything, so only the first logs commands.
        FsmtpTest::assertSame(['MAIL', 'RCPT', 'DATA', 'QUIT'], array_map(function ($c) { return strtoupper(substr($c, 0, 4)); }, array_slice($commands, 1)), 'the first connection carries one email and ends with QUIT');
        $expected = $sentence('connect_host') . ' 421 4.7.0 mail.example.test Too many connections from your IP, try again later';
        FsmtpTest::assertSame($expected, $second['message'], 'second message');
        FsmtpTest::assertSame($expected, $second['response']['message'], 'second logged message');
    });

    FsmtpTest::case('the Slack, Discord and Pushover failure alerts, built from the log row, carry the server\'s reason', function () use ($start, $stop, $send) {
        // Checks the join from a real failed row to the alert text; the formatters only pass the stored message through.
        $server = $start('data');
        try {
            $result = $send(['port' => $server['port']]);
        } finally {
            $stop($server);
        }
        $reason = '554 5.7.1 Message rejected: content looks like spam (score 9.2)';
        FsmtpTest::assert(false !== strpos($result['response']['message'], $reason), 'logged: ' . $result['response']['message']);

        // What SchedulerHandler::maybeSendNotification() hands each channel for a failed row.
        $handler = (new SmtpHandler())->setSettings(['provider' => 'smtp']);
        $row = ['to' => 'to@example.test', 'subject' => 'smtp error reason suite', 'response' => serialize($result['response'])];
        $slack = \FluentMail\App\Services\NotificationHelper::formatSlackMessageBlock($handler, $row);
        $discord = \FluentMail\App\Services\NotificationHelper::formatDiscordMessageBlock($handler, $row);
        $pushover = \FluentMail\App\Services\NotificationHelper::formatPushoverMessage($handler, $row);
        FsmtpTest::assert(false !== strpos($slack['blocks'][2]['text']['text'], $reason), 'Slack: ' . $slack['blocks'][2]['text']['text']);
        FsmtpTest::assert(false !== strpos($discord, $reason), 'Discord: ' . $discord);
        FsmtpTest::assert(false !== strpos($pushover, '<code>' . esc_html($result['response']['message']) . '</code>'), 'Pushover: ' . $pushover);
    });

    FsmtpTest::case('a plugin\'s SMTP debug output still works, and the message still carries the reason', function () use ($start, $stop, $send, $freshMailer, $sentence) {
        $mailer = $freshMailer();
        $debug = [];
        $mailer->SMTPDebug = 4;
        $mailer->Debugoutput = function ($line) use (&$debug) {
            $debug[] = $line;
        };
        $server = $start('auth');
        try {
            $result = $send(['port' => $server['port'], 'auth' => 'yes', 'username' => 'probe-user', 'password' => 'wrong-password'], $mailer);
        } finally {
            $stop($server);
        }
        $reason = '535 5.7.8 Username and Password not accepted. For more information, go to https://support.example.test/mail/?p=BadCredentials';
        FsmtpTest::assertSame($sentence('authenticate') . ' ' . $reason, $result['message'], 'thrown message');
        FsmtpTest::assert(false !== strpos(implode('', $debug), 'SERVER -> CLIENT: 535'), 'the debug output still shows the conversation');
        FsmtpTest::assert(false !== strpos(implode('', $debug), 'CLIENT -> SERVER: QUIT'), 'the debug output still shows QUIT');
    });

    FsmtpTest::case('another plugin can still send with the global PHPMailer after a failed send leaves the recording instance on it', function () use ($start, $stop, $send, $freshMailer) {
        $mailer = $freshMailer();
        $refusing = $start('auth');
        try {
            $failed = $send(['port' => $refusing['port'], 'auth' => 'yes', 'username' => 'probe-user', 'password' => 'wrong-password'], $mailer);
        } finally {
            $stop($refusing);
        }
        FsmtpTest::assertSame('failed', $failed['status'], 'FluentSMTP send');
        FsmtpTest::assert($mailer->getSMTPInstance() instanceof ServerReplies, 'the recording instance stays on the global PHPMailer');

        // A plugin sending on its own with the global object, as some do outside wp_mail().
        $log = wp_tempnam('fake-smtp-log');
        $accepting = $start('ok', $log);
        try {
            $mailer->isSMTP();
            $mailer->Host = '127.0.0.1';
            $mailer->Port = $accepting['port'];
            $mailer->SMTPAuth = false;
            $mailer->SMTPSecure = '';
            $mailer->SMTPAutoTLS = false;
            $mailer->clearAllRecipients();
            $mailer->setFrom('plugin@example.test');
            $mailer->addAddress('to@example.test');
            $mailer->Subject = 'direct send';
            $mailer->Body = 'body';
            $sent = $mailer->send();
        } finally {
            $stop($accepting);
            $commands = array_values(array_filter(array_map('trim', (array) file($log))));
            unlink($log);
        }
        FsmtpTest::assertSame(true, $sent, 'direct send');
        FsmtpTest::assert(in_array('DATA', $commands, true), 'the server received the message');
    });

    FsmtpTest::case('a translated PHPMailer sentence gets the server\'s reason once', function () use ($start, $stop, $send) {
        $german = [
            'SMTP Error: Could not authenticate.'          => 'SMTP-Fehler: Authentifizierung fehlgeschlagen.',
            'SMTP Error: The following recipients failed: ' => 'SMTP-Fehler: Die folgenden Empfänger sind nicht korrekt: ',
        ];
        $wordpressMailer = ABSPATH . WPINC . '/class-wp-phpmailer.php';
        $translate = function ($translation, $text) use ($german) {
            return isset($german[$text]) ? $german[$text] : $translation;
        };
        if (file_exists($wordpressMailer)) {
            // WordPress 6.8 and later: WP_PHPMailer translates PHPMailer's sentences with __(), class-wide.
            require_once $wordpressMailer;
            // The table is static from here on; keep the one this process had, to put it back unchanged.
            $table = new class extends \PHPMailer\PHPMailer\PHPMailer {
                public static function current()
                {
                    return static::$language;
                }

                public static function put(array $language)
                {
                    static::$language = $language;
                }
            };
            $saved = $table::current();
            add_filter('gettext', $translate, 10, 2);
            $mailer = new \WP_PHPMailer(true);
        } else {
            // Before 6.8 PHPMailer keeps them on the instance, which is where a site translating them puts them.
            $mailer = new class (true) extends \PHPMailer\PHPMailer\PHPMailer {
                public function translateTo(array $german)
                {
                    $this->language = array_merge($this->getTranslations(), array_combine(['authenticate', 'recipients_failed'], array_values($german)));
                }
            };
            $mailer->translateTo($german);
        }
        $mailer->Timeout = 10;
        try {
            $server = $start('auth');
            try {
                $login = $send(['port' => $server['port'], 'auth' => 'yes', 'username' => 'probe-user', 'password' => 'wrong-password'], $mailer);
            } finally {
                $stop($server);
            }
            $server = $start('rcpt');
            try {
                $recipient = $send(['port' => $server['port']], $mailer);
            } finally {
                $stop($server);
            }
        } finally {
            if ($mailer instanceof \WP_PHPMailer) {
                remove_filter('gettext', $translate, 10);
                $table::put($saved);
            }
        }
        FsmtpTest::assertSame('SMTP-Fehler: Authentifizierung fehlgeschlagen. 535 5.7.8 Username and Password not accepted. For more information, go to https://support.example.test/mail/?p=BadCredentials', $login['message'], 'login refused');
        FsmtpTest::assert(0 === strpos($recipient['message'], 'SMTP-Fehler: Die folgenden Empfänger sind nicht korrekt: '), 'translated sentence first: ' . $recipient['message']);
        FsmtpTest::assertSame(1, substr_count($recipient['message'], 'Recipient address rejected'), 'the server\'s text appears once: ' . $recipient['message']);
    });

    FsmtpTest::case('random replies, errors and warnings never raise a warning, never lose PHPMailer\'s message and never put the login in it; what is kept stays bounded', function () {
        // Seeded, so a failure can be replayed; the seed and case number are in the failure.
        $smtp = new class extends ServerReplies {
            public function load(array $error, $reply, array $refusals, array $warnings)
            {
                $this->error = $error;
                $this->last_reply = $reply;
                $this->refusals = $refusals;
                $this->connectionWarnings = $warnings;
                return $this;
            }

            public function note()
            {
                $this->noteState();
            }

            public function warn($message)
            {
                $this->errorHandler(E_WARNING, $message);
            }

            public function kept()
            {
                return [count($this->refusals), count($this->connectionWarnings)];
            }
        };
        $seed = 20261110;
        mt_srand($seed);
        $bytes = function ($max) {
            $s = '';
            for ($i = mt_rand(0, $max); $i > 0; $i--) {
                $s .= chr(mt_rand(0, 255));
            }
            return $s;
        };
        $pick = function (array $a) {
            return $a[mt_rand(0, count($a) - 1)];
        };
        set_error_handler(function ($no, $str, $file, $line) {
            throw new \ErrorException($str . ' at ' . $file . ':' . $line, 0, $no);
        });
        try {
            for ($i = 0; $i < 3000; $i++) {
                $user = 'u' . mt_rand(1, 999) . '@example.test';
                $pass = 'p' . mt_rand(100000, 999999);
                $secrets = [base64_encode($user), base64_encode($pass), base64_encode("\0" . $user . "\0" . $pass)];
                $text = function () use ($pick, $bytes, $secrets) {
                    $parts = [$pick(['', 'Mailbox unavailable', "two\r\nlines", "Gr\xF6\xDFe", '例え', '<a@b.example>']), $bytes(20)];
                    if (0 === mt_rand(0, 9)) {
                        $parts[] = $pick($secrets);
                    }
                    shuffle($parts);
                    return implode($pick([' ', '', "\r\n", "\t"]), $parts);
                };
                // Shapes PHPMailer produces: a reply's code is digits and its enhanced code digits or none
                // (SMTP::sendCommand()); a failed connection carries the system's errno and text (SMTP::connect()).
                $error = function () use ($pick, $text, $bytes) {
                    if (0 === mt_rand(0, 3)) {
                        return ['error' => 'Failed to connect to server', 'detail' => '', 'smtp_code' => $pick(['0', '110', '111', '10061', (string) mt_rand(0, 20000)]), 'smtp_code_ex' => $text()];
                    }
                    return [
                        'error'        => $pick(['', 'Password command failed', 'Connection failed.', 'DATA END command failed', $bytes(10)]),
                        'detail'       => $text(),
                        'smtp_code'    => $pick(['', 0, 250, 421, 450, 535, 554, mt_rand(0, 999), null]),
                        'smtp_code_ex' => $pick(['', '5.7.8', '4.4.2', '2.0.0', null]),
                    ];
                };
                $reply = function () use ($pick, $text, $bytes) {
                    return $pick(['', '221 2.0.0 Bye', $bytes(30), $pick(['421', '554', '550', '535', '999', '4x1']) . $pick([' ', '-']) . $pick(['', '5.7.1 ', '4.7.0 ']) . $text()]);
                };
                $refusals = [];
                for ($k = mt_rand(0, 3); $k > 0; $k--) {
                    $refusals[] = ['error' => $error(), 'reply' => $reply()];
                }
                $warnings = [];
                for ($k = mt_rand(0, 3); $k > 0; $k--) {
                    $warnings[] = $text();
                }
                $message = $pick(['SMTP Error: Could not authenticate.', 'SMTP Error: Data not accepted.', '', $text()]);
                $label = 'seed ' . $seed . ' case ' . $i;

                $out = $smtp->load($error(), $reply(), $refusals, $warnings)->withReason($message, $secrets);

                FsmtpTest::assert(is_string($out), $label . ': not a string');
                if ('' !== trim($message) && $out !== $message) {
                    FsmtpTest::assert(0 === strpos($out, trim($message)), $label . ': PHPMailer\'s message is not first');
                }
                foreach ($secrets as $secret) {
                    if (false === strpos($message, $secret)) {
                        FsmtpTest::assertSame(false, strpos($out, $secret), $label . ': the login is in the message');
                    }
                }
                if (wp_check_invalid_utf8($message) === $message) {
                    FsmtpTest::assertSame($out, wp_check_invalid_utf8($out), $label . ': a valid message became invalid UTF-8');
                }
            }

            // A long process (a WP-CLI run, a queue worker) notes and warns many times; what is kept stays small.
            $smtp->clearRecorded();
            for ($i = 0; $i < 1000; $i++) {
                $smtp->note();
                $smtp->warn('warning ' . $i);
            }
            FsmtpTest::assertSame([3, 5], $smtp->kept(), 'refusals and warnings kept');
        } finally {
            restore_error_handler();
            mt_srand();
        }
    });

    FsmtpTest::case('a successful send is logged as before', function () use ($start, $stop, $send) {
        $server = $start('ok');
        try {
            $result = $send(['port' => $server['port']]);
        } finally {
            $stop($server);
        }
        FsmtpTest::assertSame(null, $result['message'], 'no failure');
        FsmtpTest::assertSame('sent', $result['status'], 'log status');
        FsmtpTest::assertSame(['response' => 'OK'], $result['response'], 'logged response');
    });

    // Later files reuse the global PHPMailer; give them back the one this file found.
    $GLOBALS['phpmailer'] = $originalMailer;
};
