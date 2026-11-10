<?php
/*
 * fake-smtp.php MODE [LOGFILE]: a one-connection-at-a-time SMTP server for the
 * SMTP error tests. It listens on a free port on 127.0.0.1, prints that port on
 * its first line, and refuses at the stage MODE names:
 *
 *   greet     421 in the greeting (the server closes, RFC 5321 3.8)
 *   greet554  554 in the greeting, then waits for QUIT (RFC 5321 3.1)
 *   auth      535 to AUTH (the login is otherwise accepted with 235)
 *   echo      535 to AUTH, repeating the last line the client sent
 *   plainecho as echo, but offers only AUTH PLAIN
 *   noauth    advertises no AUTH, so a client set to authenticate cannot
 *   sender    553 to MAIL FROM
 *   rcpt      550 to RCPT TO
 *   data      554 after the message
 *   ok        accepts everything
 *   custom    answers one stage with the reply in the FAKE_SMTP_REPLY environment
 *             variable (base64 of the lines joined by "\n", so any bytes can be
 *             sent; empty sends nothing), for unusual replies. FAKE_SMTP_AT names
 *             the stage: greeting, starttls (STARTTLS is then offered), auth or
 *             eod (the end of the message, the default). With FAKE_SMTP_CLOSE=1
 *             the server closes the connection after that reply; a greeting
 *             without it then answers 503 to everything but QUIT, as Postfix
 *             does after a 554 greeting
 *   ssl       implicit TLS with a self-signed certificate, then as ok
 *   starttls  offers STARTTLS with a self-signed certificate, then as ok
 *   busy      on its first connection accepts one email and refuses the
 *             next recipient with 550, keeping the connection open; every
 *             later connection gets a 421 greeting
 *   mixed     accepts the first recipient, refuses the second with 550, then
 *             refuses the message after DATA with 554
 *   drop      accepts the first recipient, refuses the next two with 450, then
 *             answers "421 ... too many errors" and closes the connection, as
 *             Postfix does at smtpd_hard_error_limit
 *   hangup    accepts the first recipient, then closes the connection at the
 *             next RCPT TO without replying (a server or network that drops
 *             the connection)
 *   idle      accepts the first email, answers the next MAIL FROM with 421
 *             (an idle connection being closed) and exits, so nothing listens
 *
 * Reply codes follow RFC 5321 (421 service not available, 535 authentication
 * credentials invalid per RFC 4954, 550/553 mailbox and sender refusals, 554
 * transaction failed). The wording is modelled on replies measured on
 * 2026-11-09: Gmail's 535 ("Username and Password not accepted", sent as two
 * lines) and Postfix's 550/553 ("Recipient address rejected", "Sender address
 * rejected"). Hosts and addresses are fictional.
 *
 * With LOGFILE, every command line is appended to it (an AUTH line as "AUTH",
 * and nothing between DATA and the final dot), so tests can see RSET and QUIT.
 */
$mode = isset($argv[1]) ? $argv[1] : 'ok';
$customReply = 'custom' === $mode ? array_filter(explode("\n", (string) base64_decode((string) getenv('FAKE_SMTP_REPLY'))), 'strlen') : [];
$customAt = 'custom' === $mode ? (getenv('FAKE_SMTP_AT') ?: 'eod') : '';
$customClose = '1' === getenv('FAKE_SMTP_CLOSE');
$log = isset($argv[2]) ? $argv[2] : '';

$ctx = stream_context_create();
if ('ssl' === $mode || 'starttls' === $mode) {
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    $crt = openssl_csr_sign(openssl_csr_new(['commonName' => 'mail.example.test'], $key), null, $key, 1);
    openssl_x509_export($crt, $certPem);
    openssl_pkey_export($key, $keyPem);
    $pem = tempnam(sys_get_temp_dir(), 'fake-smtp-');
    file_put_contents($pem, $certPem . $keyPem);
    stream_context_set_option($ctx, 'ssl', 'local_cert', $pem);
    stream_context_set_option($ctx, 'ssl', 'verify_peer', false);
}

$server = stream_socket_server(('ssl' === $mode ? 'ssl' : 'tcp') . '://127.0.0.1:0', $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
if (!$server) {
    fwrite(STDERR, "listen: $errstr\n");
    exit(1);
}
$name = stream_socket_get_name($server, false);
echo substr($name, strrpos($name, ':') + 1), "\n";
flush();

$connections = 0;
while (true) {
    $c = @stream_socket_accept($server, -1);
    if (!$c) {
        continue;
    }
    $connections++;
    $rcptCount = 0;
    $say = function ($line) use ($c) {
        fwrite($c, $line . "\r\n");
    };
    if ('greet' === $mode || ('busy' === $mode && $connections > 1)) {
        $say('421 4.7.0 mail.example.test Too many connections from your IP, try again later');
        fclose($c);
        continue;
    }
    // Sends the custom reply; true when the connection is to close after it.
    $custom = function ($stage) use ($say, $customAt, $customReply, $customClose) {
        if ($stage !== $customAt) {
            return null;
        }
        foreach ($customReply as $replyLine) {
            $say($replyLine);
        }
        return $customClose;
    };
    if ('greeting' === $customAt && $custom('greeting')) {
        fclose($c);
        continue;
    }
    if ('greet554' === $mode || 'greeting' === $customAt) {
        if ('greet554' === $mode) {
            $say('554 5.7.1 mail.example.test No SMTP service here');
        }
        while (false !== ($line = fgets($c))) {
            if ('QUIT' === strtoupper(trim($line))) {
                $say('221 2.0.0 Bye');
                break;
            }
            $say('503 5.5.1 Error: send QUIT');
        }
        fclose($c);
        continue;
    }
    $say('220 mail.example.test ESMTP fake');
    $inData = false;
    $lastLine = '';
    while (false !== ($line = fgets($c))) {
        if ($inData) {
            if ("." === rtrim($line, "\r\n")) {
                $inData = false;
                if ('eod' === $customAt) {
                    if ($custom('eod')) {
                        break;
                    }
                    continue;
                }
                $say('data' === $mode || 'mixed' === $mode ? '554 5.7.1 Message rejected: content looks like spam (score 9.2)' : '250 2.0.0 Ok: queued as FAKE1');
                $accepted = true;
            }
            continue;
        }
        $cmd = strtoupper(substr(trim($line), 0, 4));
        if ($log) {
            file_put_contents($log, ('AUTH' === $cmd ? 'AUTH' : trim($line)) . "\n", FILE_APPEND);
        }
        if ('EHLO' === $cmd || 'HELO' === $cmd) {
            $say('250-mail.example.test');
            if ('starttls' === $mode || 'starttls' === $customAt) {
                $say('250-STARTTLS');
            }
            if ('plainecho' === $mode) {
                $say('250-AUTH PLAIN');
            } elseif ('noauth' !== $mode) {
                $say('250-AUTH PLAIN LOGIN');
            }
            $say('250 8BITMIME');
        } elseif ('STAR' === $cmd && 'starttls' === $customAt) {
            if ($custom('starttls')) {
                break;
            }
        } elseif ('STAR' === $cmd && 'starttls' === $mode) {
            $say('220 2.0.0 Ready to start TLS');
            if (!@stream_socket_enable_crypto($c, true, STREAM_CRYPTO_METHOD_TLS_SERVER)) {
                break;
            }
        } elseif ('AUTH' === $cmd) {
            // AUTH LOGIN asks for the username and the password in turn; AUTH PLAIN may carry them inline.
            $lastLine = trim($line);
            if (false !== stripos($line, 'LOGIN')) {
                $say('334 VXNlcm5hbWU6');
                $lastLine = trim((string) fgets($c));
                $say('334 UGFzc3dvcmQ6');
                $lastLine = trim((string) fgets($c));
            } elseif ('' === trim(substr(trim($line), 10))) {
                $say('334 ');
                $lastLine = trim((string) fgets($c));
            }
            if ('auth' === $customAt) {
                if ($custom('auth')) {
                    break;
                }
            } elseif ('auth' === $mode) {
                $say('535-5.7.8 Username and Password not accepted. For more information, go to');
                $say('535 5.7.8  https://support.example.test/mail/?p=BadCredentials');
            } elseif ('echo' === $mode || 'plainecho' === $mode) {
                $say('535 5.7.8 Authentication failed for ' . $lastLine);
            } else {
                $say('235 2.7.0 Accepted');
            }
        } elseif ('MAIL' === $cmd && 'idle' === $mode && !empty($accepted)) {
            $say('421 4.4.2 mail.example.test Error: timeout exceeded, closing connection');
            exit(0);
        } elseif ('MAIL' === $cmd) {
            $say('sender' === $mode ? '553 5.7.1 <sender@example.test>: Sender address rejected: not owned by user probe-user' : '250 2.1.0 Ok');
        } elseif ('RCPT' === $cmd) {
            $rcptCount++;
            if ('mixed' === $mode && $rcptCount > 1) {
                $say('550 5.1.1 <bad@example.test>: Recipient address rejected: User unknown');
                continue;
            }
            if ('hangup' === $mode && $rcptCount > 1) {
                break;
            }
            if ('drop' === $mode && $rcptCount > 1) {
                if ($rcptCount > 3) {
                    $say('421 4.7.0 mail.example.test Error: too many errors');
                    break;
                }
                $say('450 4.1.2 <bad@example.test>: Recipient address rejected: Domain not found');
                continue;
            }
            $say('rcpt' === $mode || ('busy' === $mode && $rcptCount > 1) ? '550 5.1.1 <to@example.test>: Recipient address rejected: User unknown in virtual mailbox table' : '250 2.1.5 Ok');
        } elseif ('DATA' === $cmd) {
            $say('354 End data with <CR><LF>.<CR><LF>');
            $inData = true;
        } elseif ('RSET' === $cmd || 'NOOP' === $cmd) {
            $say('250 2.0.0 Ok');
        } elseif ('QUIT' === $cmd) {
            $say('221 2.0.0 Bye');
            break;
        } else {
            $say('502 5.5.2 Error: command not recognized');
        }
    }
    fclose($c);
}
