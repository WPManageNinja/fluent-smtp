<?php

use FluentMail\App\Hooks\Handlers\BulkSendSessionHandler;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\Smtp\Handler as SmtpHandler;

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

/**
 * PHPMailer with the network step replaced: the real preSend() builds the whole
 * MIME message, and postSend() records it instead of opening a connection.
 *
 * Derived from WordPress's bundled PHPMailer (wp-includes/PHPMailer/PHPMailer.php,
 * VERSION 7.1.1 in WordPress 7.1.2, 6.9.1 in 6.5), where send() is preSend()
 * followed by postSend() and both are public in both versions. Everything a
 * handler adds or changes still goes through PHPMailer's own code; only the
 * SMTP conversation (or mail() call) is skipped.
 */
class FsmtpSuiteMimeCapture extends \PHPMailer\PHPMailer\PHPMailer
{
    /**
     * The MIME message the last send produced, or null if none reached postSend().
     *
     * @var string|null
     */
    public $mime = null;

    /**
     * How many times postSend() was reached.
     *
     * @var int
     */
    public $sends = 0;

    /**
     * Record the message instead of sending it.
     *
     * @return bool
     */
    public function postSend()
    {
        ++$this->sends;
        $this->mime = $this->getSentMIMEMessage();
        return true;
    }
}

/**
 * The real toSend handler with its cURL call replaced (toSend sends through
 * cURL, not the WordPress HTTP API, so the suite's interceptor cannot see it).
 * Same approach as FsmtpSuiteToSendProbe in connection-behavior.php.
 */
class FsmtpSuiteToSendAttachmentProbe extends \FluentMail\App\Services\Mailer\Providers\ToSend\Handler
{
    /**
     * The JSON body the handler would have posted.
     *
     * @var string|null
     */
    public $capturedBody = null;

    /**
     * Record the request instead of sending it, answering as toSend does on success.
     *
     * @param string $url
     * @param string $jsonBody
     * @return array
     */
    protected function sendViaCurl($url, $jsonBody)
    {
        $this->capturedBody = $jsonBody;

        return [
            'code' => 200,
            'body' => json_encode(['message_id' => 'suite-message-id']),
        ];
    }
}

/**
 * The real SMTP handler with its logging tail replaced, so a case sees the
 * send's result and nothing is written to the email log.
 */
class FsmtpSuiteSmtpCaptureHandler extends SmtpHandler
{
    /**
     * Return the result instead of logging it.
     *
     * @param mixed $response
     * @return mixed
     */
    public function handleResponse($response)
    {
        return $response;
    }
}

/**
 * The real PHP mail() handler with its logging tail replaced, as
 * FsmtpSuiteDefaultMailProbeHandler in connection-behavior.php does.
 */
class FsmtpSuiteDefaultMailCaptureHandler extends \FluentMail\App\Services\Mailer\Providers\DefaultMail\Handler
{
    /**
     * Report success instead of logging it.
     *
     * @return string
     */
    protected function handleSuccess()
    {
        return 'sent';
    }

    /**
     * Report the failure instead of logging it.
     *
     * @param \Exception $exception
     * @return string
     */
    protected function handleFailure($exception)
    {
        return 'failed: ' . preg_replace('/[^\x20-\x7E]/', '?', substr($exception->getMessage(), 0, 40));
    }
}

/**
 * The real Outlook handler run against the harness HTTP interceptor, as
 * FsmtpSuiteOutlookProbe in connection-behavior.php does.
 */
class FsmtpSuiteOutlookAttachmentProbe extends \FluentMail\App\Services\Mailer\Providers\Outlook\Handler
{
    /**
     * Send one message through the real postSend().
     *
     * @param \PHPMailer\PHPMailer\PHPMailer $phpMailer
     * @param array                          $settings
     * @return mixed
     */
    public function runPostSend($phpMailer, $settings)
    {
        $this->phpMailer = $phpMailer;
        $this->setSettings($settings);
        $this->attributes = $this->setAttributes();
        $phpMailer->preSend();

        return $this->postSend();
    }

    /**
     * Return the result instead of logging it.
     *
     * @param mixed $response
     * @return mixed
     */
    public function handleResponse($response)
    {
        return $response;
    }
}

return function () {
    /*
     * Attachments reach the recipient once, with their name, type and bytes
     * intact, however the sender added them: a file path, data held in memory
     * (addStringAttachment(), as Germanized and the #437 reporter's invoice
     * plugin do), or an inline image. The cases drive the real handlers with
     * PHPMailer's real MIME builder and compare decoded parts by hash.
     */

    // Every byte value, so an encoding that mangles any byte shows up as a hash mismatch.
    // Deliberately not a real PDF: content sniffing (mime_content_type()) and the
    // type PHPMailer records then disagree, which is what shows which one is used.
    $binary = implode('', array_map('chr', range(0, 255))) . implode('', array_map('chr', range(255, 0, -1)));

    $fixtureDir = trailingslashit(wp_upload_dir()['basedir']) . FsmtpTest::uniq('fsmtp-attachments');

    /* Write a fixture file in the suite's own uploads folder and return its path. */
    $fixture = function ($name, $bytes) use ($fixtureDir) {
        if (!is_dir($fixtureDir)) {
            wp_mkdir_p($fixtureDir);
        }
        $path = $fixtureDir . '/' . $name;
        file_put_contents($path, $bytes);
        return $path;
    };

    $removeFixtures = function () use ($fixtureDir) {
        if (is_dir($fixtureDir)) {
            foreach ((array) glob($fixtureDir . '/*') as $file) {
                unlink($file);
            }
            rmdir($fixtureDir);
        }
    };

    /*
     * Flatten a MIME message into its leaf parts. No tree: every boundary line
     * starts a chunk, a chunk is headers, a blank line, then the body. Enough to
     * count parts, read their headers and decode their bodies.
     */
    $mimeParts = function ($mime) {
        preg_match_all('/boundary="?([^";\r\n]+)"?/i', $mime, $found);
        $boundaries = array_unique($found[1]);

        $chunks = [];
        $current = null;
        foreach (preg_split('/\r\n|\n/', $mime) as $line) {
            $isBoundary = false;
            foreach ($boundaries as $boundary) {
                if ($line === '--' . $boundary || $line === '--' . $boundary . '--') {
                    $isBoundary = true;
                    break;
                }
            }
            if ($isBoundary) {
                if ($current !== null) {
                    $chunks[] = $current;
                }
                $current = [];
                continue;
            }
            if ($current !== null) {
                $current[] = $line;
            }
        }

        $parts = [];
        foreach ($chunks as $chunk) {
            // No headers means the gap after a nested part closes, not a part.
            $blank = array_search('', $chunk, true);
            if ($blank === false || $blank === 0) {
                continue;
            }

            $headers = [];
            $last = null;
            foreach (array_slice($chunk, 0, $blank) as $line) {
                if ($last !== null && isset($line[0]) && ($line[0] === ' ' || $line[0] === "\t")) {
                    $headers[$last] .= ' ' . trim($line);
                    continue;
                }
                if (strpos($line, ':') === false) {
                    continue;
                }
                list($key, $value) = explode(':', $line, 2);
                $last = strtolower(trim($key));
                $headers[$last] = trim($value);
            }

            $type = strtolower(trim(strtok(isset($headers['content-type']) ? $headers['content-type'] : 'text/plain', ';')));
            if (strpos($type, 'multipart/') === 0) {
                continue;
            }

            $disposition = isset($headers['content-disposition']) ? $headers['content-disposition'] : '';
            $filename = null;
            if (preg_match('/filename="((?:[^"\\\\]|\\\\.)*)"/i', $disposition, $match)) {
                $filename = str_replace(['\\"', '\\\\'], ['"', '\\'], $match[1]);
            } elseif (preg_match('/filename=([^;\s]+)/i', $disposition, $match)) {
                $filename = $match[1];
            }
            if ($filename !== null && strpos($filename, '=?') !== false) {
                $filename = iconv_mime_decode($filename, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            }

            $body = implode("\r\n", array_slice($chunk, $blank + 1));
            $encoding = strtolower(isset($headers['content-transfer-encoding']) ? $headers['content-transfer-encoding'] : '');
            if ($encoding === 'base64') {
                $body = base64_decode(preg_replace('/\s+/', '', $body));
            } elseif ($encoding === 'quoted-printable') {
                $body = quoted_printable_decode($body);
            }

            $parts[] = [
                'type'        => $type,
                'disposition' => $disposition === '' ? null : strtolower(trim(strtok($disposition, ';'))),
                'filename'    => $filename,
                'cid'         => isset($headers['content-id']) ? trim($headers['content-id'], '<> ') : null,
                'md5'         => md5($body),
            ];
        }

        return $parts;
    };

    /* The parts a recipient sees as files: anything with a disposition. */
    $fileParts = function (array $parts) {
        return array_values(array_filter($parts, function ($part) {
            return $part['disposition'] !== null;
        }));
    };

    /*
     * Send through the real Other SMTP handler. $build adds attachments the way
     * wp_mail() or a phpmailer_init listener would, to the same PHPMailer object
     * the handler then receives.
     */
    $smtpSend = function (callable $build) use ($mimeParts, $fileParts) {
        $mailer = new FsmtpSuiteMimeCapture(true);
        // wp_mail() sets the blog charset (UTF-8) before any handler runs; these
        // cases bypass wp_mail(), and PHPMailer's own default is iso-8859-1,
        // which would encode a non-ASCII filename under the wrong label.
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom('sender@example.test', 'Suite');
        $mailer->addAddress('to@example.test');
        $mailer->Subject = 'attachments suite';
        $mailer->Body = '<p>body</p>';
        $mailer->isHTML(true);

        $build($mailer);

        try {
            $result = (new FsmtpSuiteSmtpCaptureHandler())
                ->setSettings([
                    'provider'         => 'smtp',
                    'sender_email'     => 'sender@example.test',
                    'sender_name'      => '',
                    'host'             => 'smtp.invalid',
                    'port'             => 2525,
                    'auth'             => 'no',
                    'encryption'       => 'none',
                    'auto_tls'         => 'no',
                    'force_from_name'  => 'no',
                    'force_from_email' => 'no',
                    'return_path'      => 'no',
                ])
                ->setPhpMailer($mailer)
                ->send();
        } finally {
            BaseHandler::forgetSmtpTransportClaim();
            BulkSendSessionHandler::closeConnection();
        }

        // A failure message can quote the attachment's bytes: keep a printable prefix only.
        $failure = is_wp_error($result)
            ? preg_replace('/[^\x20-\x7E]/', '?', substr($result->get_error_message(), 0, 40))
            : null;
        $parts = $mailer->mime === null ? [] : $mimeParts($mailer->mime);

        return [
            'failure' => $failure,
            'sends'   => $mailer->sends,
            'parts'   => $parts,
            'files'   => $fileParts($parts),
        ];
    };

    /* One summary per file part: name, disposition, type, content hash. */
    $summary = function (array $files) {
        return array_map(function ($part) {
            return [$part['filename'], $part['disposition'], $part['type'], $part['md5']];
        }, $files);
    };

    try {
        $pdfPath = $fixture('suite-document.pdf', $binary);
        $pngPath = $fixture('suite-logo.png', "\x89PNG\r\n\x1a\n" . $binary);
        $icsPath = $fixture('suite-event.bin', "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n");
        $otherPath = $fixture('suite-other.pdf', strrev($binary));

        FsmtpTest::case('SMTP: a file path is delivered once with its name and exact bytes', function () use ($smtpSend, $summary, $pdfPath, $binary) {
            $sent = $smtpSend(function ($mailer) use ($pdfPath) {
                $mailer->addAttachment($pdfPath);
            });

            FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
            FsmtpTest::assertSame(
                [['suite-document.pdf', 'attachment', 'application/pdf', md5($binary)]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: a custom name from wp_mail() reaches the message', function () use ($smtpSend, $summary, $pdfPath, $binary) {
            // wp_mail() passes the attachments array key as addAttachment()'s name.
            $sent = $smtpSend(function ($mailer) use ($pdfPath) {
                $mailer->addAttachment($pdfPath, 'Invoice 1001.pdf');
            });

            FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
            FsmtpTest::assertSame(
                [['Invoice 1001.pdf', 'attachment', 'application/pdf', md5($binary)]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: a name with a double quote is delivered once', function () use ($smtpSend, $summary, $pdfPath, $binary) {
            // 2.4.0 re-added attachments with a cleaned name, so PHPMailer no
            // longer recognised the copy and this arrived twice.
            $sent = $smtpSend(function ($mailer) use ($pdfPath) {
                $mailer->addAttachment($pdfPath, 'Invoice "1002".pdf');
            });

            FsmtpTest::assertSame(
                [['Invoice "1002".pdf', 'attachment', 'application/pdf', md5($binary)]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: a name with a line break is delivered once without it', function () use ($smtpSend, $summary, $pdfPath, $binary) {
            $sent = $smtpSend(function ($mailer) use ($pdfPath) {
                $mailer->addAttachment($pdfPath, "Line\r\nBreak.pdf");
            });

            FsmtpTest::assertSame(
                [['LineBreak.pdf', 'attachment', 'application/pdf', md5($binary)]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: a non-ASCII name is delivered once and decodes back', function () use ($smtpSend, $summary, $pdfPath, $binary) {
            $sent = $smtpSend(function ($mailer) use ($pdfPath) {
                $mailer->addAttachment($pdfPath, 'Rechnung für März.pdf');
            });

            FsmtpTest::assertSame(
                [['Rechnung für März.pdf', 'attachment', 'application/pdf', md5($binary)]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: an in-memory attachment is delivered instead of failing the email', function () use ($smtpSend, $summary, $binary) {
            // The #437 case: the bytes were re-added as if they were a file path.
            $sent = $smtpSend(function ($mailer) use ($binary) {
                $mailer->addStringAttachment($binary, 'invoice-inmemory.pdf', 'base64', 'application/pdf');
            });

            FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
            FsmtpTest::assertSame(1, $sent['sends'], 'messages sent');
            FsmtpTest::assertSame(
                [['invoice-inmemory.pdf', 'attachment', 'application/pdf', md5($binary)]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: an in-memory attachment without a name is still delivered', function () use ($smtpSend, $summary, $binary) {
            $sent = $smtpSend(function ($mailer) use ($binary) {
                $mailer->addStringAttachment($binary, '');
            });

            FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
            FsmtpTest::assertSame(
                [[null, 'attachment', 'application/octet-stream', md5($binary)]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: a file and an in-memory attachment are both delivered', function () use ($smtpSend, $summary, $pdfPath, $binary) {
            $sent = $smtpSend(function ($mailer) use ($pdfPath, $binary) {
                $mailer->addAttachment($pdfPath);
                $mailer->addStringAttachment(strrev($binary), 'second.pdf', 'base64', 'application/pdf');
            });

            FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
            FsmtpTest::assertSame(
                [
                    ['suite-document.pdf', 'attachment', 'application/pdf', md5($binary)],
                    ['second.pdf', 'attachment', 'application/pdf', md5(strrev($binary))],
                ],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: an inline image is delivered once, inline, with its Content-ID', function () use ($smtpSend, $summary, $pngPath, $binary) {
            $sent = $smtpSend(function ($mailer) use ($pngPath) {
                $mailer->Body = '<p><img src="cid:suite-logo"></p>';
                $mailer->addEmbeddedImage($pngPath, 'suite-logo', 'logo.png', 'base64', 'image/png');
            });

            FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
            FsmtpTest::assertSame(
                [['logo.png', 'inline', 'image/png', md5("\x89PNG\r\n\x1a\n" . $binary)]],
                $summary($sent['files']),
                'file parts (no ordinary-attachment copy)'
            );
            FsmtpTest::assertSame('suite-logo', isset($sent['files'][0]) ? $sent['files'][0]['cid'] : null, 'Content-ID');
        });

        FsmtpTest::case('SMTP: an HTML email with a plain-text version keeps both and the attachment', function () use ($smtpSend, $summary, $pdfPath, $binary) {
            // WooCommerce sets AltBody on phpmailer_init for its HTML emails.
            $sent = $smtpSend(function ($mailer) use ($pdfPath) {
                $mailer->AltBody = 'plain body';
                $mailer->addAttachment($pdfPath, 'Invoice 1001.pdf');
            });

            $bodyTypes = array_map(function ($part) {
                return $part['type'];
            }, array_values(array_filter($sent['parts'], function ($part) {
                return $part['disposition'] === null;
            })));

            FsmtpTest::assertSame(['text/plain', 'text/html'], $bodyTypes, 'body parts');
            FsmtpTest::assertSame(
                [['Invoice 1001.pdf', 'attachment', 'application/pdf', md5($binary)]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: a declared type is delivered as declared, once', function () use ($smtpSend, $summary, $icsPath) {
            // A listener can add a file with its own name, encoding and type. The
            // path's extension (.bin) deliberately disagrees with the declared
            // type: a copy that takes its type from the path would differ, so
            // PHPMailer would send both.
            $sent = $smtpSend(function ($mailer) use ($icsPath) {
                $mailer->addAttachment($icsPath, 'event.ics', 'base64', 'text/calendar');
            });

            FsmtpTest::assertSame(
                [['event.ics', 'attachment', 'text/calendar', md5("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n")]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: a listener that clears and re-adds attachments decides what is sent', function () use ($smtpSend, $summary, $pdfPath, $otherPath, $binary) {
            // A phpmailer_init listener may clear the list and add its own; the
            // object's final list is what is sent. This does not catch the old
            // re-adding loop: PHPMailer drops a re-added record identical to one
            // it already has.
            $sent = $smtpSend(function ($mailer) use ($pdfPath, $otherPath) {
                $mailer->addAttachment($pdfPath);
                $mailer->clearAttachments();
                $mailer->addAttachment($otherPath);
            });

            FsmtpTest::assertSame(
                [['suite-other.pdf', 'attachment', 'application/pdf', md5(strrev($binary))]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: a file in the system temp folder is delivered', function () use ($smtpSend, $summary, $binary) {
            // Amelia's WordPress-mail mode writes attachments to tempnam() files.
            $path = tempnam(sys_get_temp_dir(), 'fsmtp-suite-');
            file_put_contents($path, $binary);
            try {
                $sent = $smtpSend(function ($mailer) use ($path) {
                    $mailer->addAttachment($path, 'ticket.pdf');
                });
            } finally {
                unlink($path);
            }

            FsmtpTest::assertSame(
                [['ticket.pdf', 'attachment', 'application/octet-stream', md5($binary)]],
                $summary($sent['files']),
                'file parts'
            );
        });

        FsmtpTest::case('SMTP: the same file added twice is delivered once', function () use ($smtpSend, $summary, $pdfPath, $binary) {
            // PHPMailer's attachAll() skips a record identical to one already
            // written; that, not FluentSMTP, is why the old re-add loop left
            // plain paths alone (measured on 2.4.1 in the harness).
            $sent = $smtpSend(function ($mailer) use ($pdfPath) {
                $mailer->addAttachment($pdfPath);
                $mailer->addAttachment($pdfPath);
            });

            FsmtpTest::assertSame(
                [['suite-document.pdf', 'attachment', 'application/pdf', md5($binary)]],
                $summary($sent['files']),
                'file parts'
            );
        });

        /* The record a real PHPMailer stores for one attachment added by $add. */
        $rowFor = function (callable $add) {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $add($mailer);
            $rows = $mailer->getAttachments();
            return $rows[0];
        };

        /* Run $callback and return the exception message it throws, or null. */
        $thrown = function (callable $callback) {
            try {
                $callback();
            } catch (\Exception $e) {
                return $e->getMessage();
            }
            return null;
        };

        /*
         * PHP mail() and Outlook send PHPMailer's own message (Outlook only when
         * there is no Bcc), which carries in-memory data itself; Outlook's Graph
         * payload already used in-memory data as it is. These pin that.
         */
        FsmtpTest::case('PHP mail: in-memory data with NUL bytes is delivered', function () use ($mimeParts, $fileParts, $summary, $pdfPath, $binary) {
            $mailer = new FsmtpSuiteMimeCapture(true);
            $mailer->CharSet = 'UTF-8';
            $mailer->isMail();
            $mailer->setFrom('sender@example.test', 'Suite');
            $mailer->addAddress('to@example.test');
            $mailer->Subject = 'attachments suite';
            $mailer->Body = 'body';
            $mailer->addAttachment($pdfPath, 'Invoice 1001.pdf');
            $mailer->addStringAttachment($binary, 'invoice.pdf', 'base64', 'application/pdf');

            try {
                $result = (new FsmtpSuiteDefaultMailCaptureHandler())
                    ->setSettings([
                        'provider'         => 'default',
                        'sender_email'     => 'sender@example.test',
                        'sender_name'      => '',
                        'force_from_name'  => 'no',
                        'force_from_email' => 'no',
                    ])
                    ->setPhpMailer($mailer)
                    ->send();
            } finally {
                BaseHandler::forgetSmtpTransportClaim();
                BulkSendSessionHandler::closeConnection();
            }

            FsmtpTest::assertSame('sent', $result, 'send result');
            FsmtpTest::assertSame([
                ['Invoice 1001.pdf', 'attachment', 'application/pdf', md5($binary)],
                ['invoice.pdf', 'attachment', 'application/pdf', md5($binary)],
            ], $summary($fileParts($mailer->mime === null ? [] : $mimeParts($mailer->mime))), 'file parts');
        });

        /* Send through the real Outlook handler; return the single Graph request. */
        $outlookRequest = function (callable $configure) {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom('sender@example.test', 'Suite');
            $mailer->addAddress('to@example.test');
            $mailer->Subject = 'attachments suite';
            $mailer->Body = 'body';
            $configure($mailer);

            $headersClass = class_exists('\WpOrg\Requests\Utility\CaseInsensitiveDictionary')
                ? '\WpOrg\Requests\Utility\CaseInsensitiveDictionary'
                : 'Requests_Utility_CaseInsensitiveDictionary';
            FsmtpTest::interceptHttp(function ($url) use ($headersClass) {
                if (strpos($url, 'https://graph.microsoft.com/v1.0/me/sendMail') !== 0) {
                    return null;
                }
                return ['headers' => new $headersClass(['request-id' => 'suite']), 'body' => '', 'response' => ['code' => 202, 'message' => 'Accepted'], 'cookies' => [], 'filename' => null];
            });
            try {
                $result = (new FsmtpSuiteOutlookAttachmentProbe())->runPostSend($mailer, [
                    'provider'      => 'outlook',
                    'sender_email'  => 'sender@example.test',
                    'sender_name'   => 'Suite',
                    'key_store'     => 'db',
                    'client_id'     => 'suite-client',
                    'client_secret' => 'suite-secret',
                    'access_token'  => 'suite-access-token',
                    'refresh_token' => 'suite-refresh-token',
                    'expire_stamp'  => time() + 3600,
                ]);
                $requests = FsmtpTest::httpRequests();
            } finally {
                FsmtpTest::interceptHttp();
            }

            FsmtpTest::assert(!is_wp_error($result), 'Outlook send failed');
            FsmtpTest::assertSame(1, count($requests), 'Graph requests made by one send');
            return $requests[0];
        };

        FsmtpTest::case('Outlook: in-memory data with NUL bytes arrives in its raw MIME message', function () use ($outlookRequest, $mimeParts, $fileParts, $summary, $binary) {
            $request = $outlookRequest(function ($mailer) use ($binary) {
                $mailer->addStringAttachment($binary, 'invoice.pdf', 'base64', 'application/pdf');
            });
            $mime = (string) base64_decode(preg_replace('/\s+/', '', $request['args']['body']), true);
            FsmtpTest::assertSame([['invoice.pdf', 'attachment', 'application/pdf', md5($binary)]], $summary($fileParts($mimeParts($mime))), 'file parts');
        });

        FsmtpTest::case('Outlook: in-memory data with NUL bytes arrives in its Graph payload', function () use ($outlookRequest, $binary) {
            $request = $outlookRequest(function ($mailer) use ($binary) {
                $mailer->addBCC('hidden@example.test');
                $mailer->addStringAttachment($binary, 'invoice.pdf', 'base64', 'application/pdf');
            });
            $payload = json_decode($request['args']['body'], true);
            $rows = array_map(function ($a) {
                return [$a['name'], $a['contentType'], md5(base64_decode($a['contentBytes']))];
            }, isset($payload['message']['attachments']) ? $payload['message']['attachments'] : []);
            FsmtpTest::assertSame([['invoice.pdf', 'application/pdf', md5($binary)]], $rows, 'Graph attachments');
        });

        FsmtpTest::case('helper: attachment names for files and in-memory data', function () use ($rowFor, $pdfPath, $binary) {
            $name = function (callable $add) use ($rowFor) {
                return BaseHandler::getAttachmentName($rowFor($add));
            };

            FsmtpTest::assertSame('suite-document.pdf', $name(function ($m) use ($pdfPath) {
                $m->addAttachment($pdfPath);
            }), 'file without a name');
            FsmtpTest::assertSame('Invoice 1001.pdf', $name(function ($m) use ($pdfPath) {
                $m->addAttachment($pdfPath, 'Invoice 1001.pdf');
            }), 'file with a name');
            FsmtpTest::assertSame('invoice.pdf', $name(function ($m) use ($binary) {
                $m->addStringAttachment($binary, 'invoice.pdf');
            }), 'in-memory with a name');
            FsmtpTest::assertSame('Invoice 1002.pdf', $name(function ($m) use ($binary) {
                $m->addStringAttachment($binary, 'Invoice "1002".pdf');
            }), 'in-memory name made header-safe');
            FsmtpTest::assertSame('attachment.pdf', $name(function ($m) use ($binary) {
                $m->addStringAttachment($binary, '', 'base64', 'application/pdf');
            }), 'in-memory without a name, typed');
            FsmtpTest::assertSame('attachment', $name(function ($m) use ($binary) {
                $m->addStringAttachment($binary, '');
            }), 'in-memory without a name or type');
            // Data that looks like a path must never become the name.
            FsmtpTest::assertSame('attachment', $name(function ($m) {
                $m->addStringAttachment('/var/private/../../etc/secret-name.pdf', '');
            }), 'in-memory data that looks like a path');
        });

        FsmtpTest::case('helper: attachment contents for files and in-memory data', function () use ($rowFor, $thrown, $pdfPath, $binary) {
            FsmtpTest::assertSame(md5($binary), md5(BaseHandler::attachmentContent($rowFor(function ($m) use ($binary) {
                $m->addStringAttachment($binary, 'invoice.pdf');
            }))), 'in-memory bytes');
            FsmtpTest::assertSame(md5($binary), md5(BaseHandler::attachmentContent($rowFor(function ($m) use ($pdfPath) {
                $m->addAttachment($pdfPath);
            }))), 'file bytes');

            // PHPMailer refuses an unreadable path at add time, so these rows are
            // what a stale log or a listener could still hand a provider.
            $fileRow = function ($path) {
                return [$path, basename($path), basename($path), 'base64', 'application/pdf', false, 'attachment', basename($path)];
            };
            FsmtpTest::assert(null !== $thrown(function () use ($fileRow, $pdfPath) {
                BaseHandler::attachmentContent($fileRow($pdfPath . '.missing'));
            }), 'a missing file must throw');
            FsmtpTest::assert(null !== $thrown(function () use ($fileRow, $pdfPath) {
                BaseHandler::attachmentContent($fileRow(dirname($pdfPath)));
            }), 'a directory must throw');
            FsmtpTest::assertSame(
                'Access to this file location is restricted for security reasons',
                $thrown(function () use ($fileRow) {
                    BaseHandler::attachmentContent($fileRow(ABSPATH . 'wp-config.php'));
                }),
                'wp-config.php is blocked'
            );
            // A NUL byte made realpath() throw a ValueError on PHP 8, which no
            // provider's catch (\Exception) could stop.
            FsmtpTest::assertSame('Invalid file path', $thrown(function () use ($fileRow, $pdfPath) {
                BaseHandler::attachmentContent($fileRow($pdfPath . "\0.pdf"));
            }), 'a path with a NUL byte throws a catchable Exception');

            $blockFixtures = function ($paths) use ($pdfPath) {
                $paths[] = dirname($pdfPath);
                return $paths;
            };
            add_filter('fluentsmtp_attachment_blocked_paths', $blockFixtures);
            try {
                $message = $thrown(function () use ($fileRow, $pdfPath) {
                    BaseHandler::attachmentContent($fileRow($pdfPath));
                });
            } finally {
                remove_filter('fluentsmtp_attachment_blocked_paths', $blockFixtures);
            }
            FsmtpTest::assertSame('Access to this file location is restricted for security reasons', $message, 'a filtered blocked folder');

            $notAnArray = function () {
                return 'not an array';
            };
            add_filter('fluentsmtp_attachment_blocked_paths', $notAnArray);
            try {
                $bytes = BaseHandler::attachmentContent($fileRow($pdfPath));
            } finally {
                remove_filter('fluentsmtp_attachment_blocked_paths', $notAnArray);
            }
            FsmtpTest::assertSame(md5($binary), md5($bytes), 'a broken blocklist filter falls back to reading');
        });

        FsmtpTest::case('helper: attachment types come from the record, then the name', function () use ($rowFor, $pdfPath, $icsPath, $binary) {
            $type = function (callable $add) use ($rowFor) {
                return BaseHandler::attachmentType($rowFor($add));
            };

            FsmtpTest::assertSame('text/calendar', $type(function ($m) use ($icsPath) {
                $m->addAttachment($icsPath, 'event.ics', 'base64', 'text/calendar');
            }), 'declared type');
            FsmtpTest::assertSame('text/calendar', $type(function ($m) use ($icsPath) {
                $m->addAttachment($icsPath, 'event.txt', 'base64', 'text/calendar');
            }), 'declared type wins over the name\'s type');
            FsmtpTest::assertSame('application/pdf', $type(function ($m) use ($pdfPath) {
                $m->addAttachment($pdfPath);
            }), 'type from the path');
            FsmtpTest::assertSame('application/pdf', $type(function ($m) use ($binary) {
                $m->addStringAttachment($binary, 'invoice.pdf');
            }), 'in-memory type from its name');

            // A path without an extension records only the generic type; the
            // delivered name decides (the old handlers sniffed the contents).
            $bare = tempnam(sys_get_temp_dir(), 'fsmtp-suite-');
            file_put_contents($bare, $binary);
            try {
                FsmtpTest::assertSame('application/pdf', $type(function ($m) use ($bare) {
                    $m->addAttachment($bare, 'ticket.pdf');
                }), 'generic type, named .pdf');
                FsmtpTest::assertSame('application/octet-stream', $type(function ($m) use ($bare) {
                    $m->addAttachment($bare, 'ticket');
                }), 'generic type, no extension anywhere');
            } finally {
                unlink($bare);
            }
        });

        /*
         * API providers. Each is driven through its real handler with the HTTP
         * call intercepted (the pattern in display-name-commas.php), answered
         * with the status its own postSend() treats as success, and the
         * attachments are decoded back out of the request it built.
         */

        /* Read JSON attachment rows: [list key path, name key, type key or null, base64 content key]. */
        $jsonRows = function (array $path, $nameKey, $typeKey, $contentKey) {
            return function ($request) use ($path, $nameKey, $typeKey, $contentKey) {
                $node = json_decode($request['body'], true);
                foreach ($path as $key) {
                    $node = isset($node[$key]) ? $node[$key] : [];
                }
                return array_map(function ($row) use ($nameKey, $typeKey, $contentKey) {
                    return [
                        $row[$nameKey],
                        $typeKey === null ? null : $row[$typeKey],
                        md5(base64_decode(preg_replace('/\s+/', '', $row[$contentKey]))),
                    ];
                }, (array) $node);
            };
        };

        /* Read multipart/form-data file parts whose field name matches $field. */
        $formRows = function ($field) {
            return function ($request) use ($field) {
                $headers = array_change_key_case((array) $request['headers'], CASE_LOWER);
                $contentType = isset($headers['content-type']) ? $headers['content-type'] : '';
                if (!preg_match('/boundary=(\S+)/', $contentType, $match)) {
                    return [];
                }
                $rows = [];
                foreach (explode('--' . $match[1], $request['body']) as $chunk) {
                    if (!preg_match('/name="' . $field . '[^"]*"; filename="([^"]*)"\r\n(?:Content-Type: ([^\r\n]+)\r\n)?\r\n/', $chunk, $head, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }
                    $bytes = substr($chunk, $head[0][1] + strlen($head[0][0]));
                    $bytes = substr($bytes, 0, -2); // the part's closing CRLF
                    $rows[] = [$head[1][0], isset($head[2]) ? $head[2][0] : null, md5($bytes)];
                }
                return $rows;
            };
        };

        $providers = [
            'SendGrid'   => ['class' => \FluentMail\App\Services\Mailer\Providers\SendGrid\Handler::class, 'typed' => true, 'url' => 'https://api.sendgrid.com/', 'status' => 202, 'settings' => [], 'rows' => $jsonRows(['attachments'], 'filename', 'type', 'content')],
            'Postmark'   => ['class' => \FluentMail\App\Services\Mailer\Providers\Postmark\Handler::class, 'typed' => true, 'url' => 'https://api.postmarkapp.com/', 'status' => 200, 'settings' => [], 'rows' => $jsonRows(['Attachments'], 'Name', 'ContentType', 'Content')],
            'SparkPost'  => ['class' => \FluentMail\App\Services\Mailer\Providers\SparkPost\Handler::class, 'typed' => true, 'url' => 'https://api.sparkpost.com/', 'status' => 200, 'settings' => [], 'rows' => $jsonRows(['content', 'attachments'], 'name', 'type', 'data')],
            'Smtp2Go'    => ['class' => \FluentMail\App\Services\Mailer\Providers\Smtp2Go\Handler::class, 'typed' => true, 'url' => 'https://api.smtp2go.com/', 'status' => 200, 'settings' => [], 'rows' => $jsonRows(['attachments'], 'filename', 'mimetype', 'fileblob')],
            // PepiPost's getBody() raises its own warning on every send (tests/FIX-PLAN.md).
            'PepiPost'   => ['knownWarning' => 'Undefined property: PHPMailer\\PHPMailer\\PHPMailer::$contentType', 'class' => \FluentMail\App\Services\Mailer\Providers\PepiPost\Handler::class, 'typed' => false, 'url' => 'https://api.pepipost.com/', 'status' => 202, 'settings' => [], 'rows' => $jsonRows(['attachments'], 'name', null, 'content')],
            'Cloudflare' => ['class' => \FluentMail\App\Services\Mailer\Providers\Cloudflare\Handler::class, 'typed' => true, 'url' => 'https://api.cloudflare.com/', 'status' => 200, 'settings' => ['account_id' => 'suite-account'], 'rows' => $jsonRows(['attachments'], 'filename', 'type', 'content')],
            'SendInBlue' => ['class' => \FluentMail\App\Services\Mailer\Providers\SendInBlue\Handler::class, 'typed' => false, 'url' => 'https://api.brevo.com/', 'status' => 201, 'settings' => [], 'rows' => $jsonRows(['attachment'], 'name', null, 'content')],
            'Mailgun'    => ['class' => \FluentMail\App\Services\Mailer\Providers\Mailgun\Handler::class, 'typed' => false, 'url' => 'https://api.mailgun.net/', 'status' => 200, 'settings' => ['domain_name' => 'mg.example.test', 'region' => 'us'], 'rows' => $formRows('attachment')],
            'ElasticMail' => ['class' => \FluentMail\App\Services\Mailer\Providers\ElasticMail\Handler::class, 'typed' => true, 'url' => 'https://api.elasticemail.com/', 'status' => 200, 'settings' => ['mail_type' => 'transactional'], 'rows' => $formRows('attachments')],
            'ToSend'     => ['class' => FsmtpSuiteToSendAttachmentProbe::class, 'typed' => true, 'url' => null, 'status' => 200, 'settings' => [], 'rows' => $jsonRows(['attachments'], 'name', 'type', 'content')],
        ];

        /*
         * Send one email through a provider's real handler. Returns the send's
         * failure (printable prefix only, or null) and the attachment rows it
         * put in the request as [name, type, md5 of the decoded bytes].
         */
        $apiSend = function ($spec, callable $build) {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom('sender@example.test', 'Suite');
            $mailer->addAddress('to@example.test');
            $mailer->Subject = 'attachments suite';
            $mailer->Body = '<p>body</p>';
            $mailer->isHTML(true);
            $build($mailer);

            $table = FsmtpFactory::emailLogTable();
            $redirect = FsmtpFactory::productionLogTableRedirect($table);
            $request = null;
            FsmtpTest::interceptHttp(function ($url, $args) use (&$request, $spec) {
                if ($spec['url'] === null || strpos($url, $spec['url']) !== 0) {
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
                    'response' => ['code' => $spec['status'], 'message' => 'OK'],
                    'cookies'  => [],
                    'filename' => null,
                ];
            });

            $handler = new $spec['class']();
            $failure = null;

            // Only the one documented warning is taken out of the notice fuse, and
            // it is reported as a KNOWN-FAILURE below; anything else still fails.
            $knownSeen = false;
            $previous = null;
            if (!empty($spec['knownWarning'])) {
                $previous = set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$knownSeen, &$previous, $spec) {
                    if (strpos($errstr, $spec['knownWarning']) !== false && strpos(str_replace('\\', '/', $errfile), 'Providers/PepiPost/Handler.php') !== false) {
                        $knownSeen = true;
                        return true;
                    }
                    return $previous ? call_user_func($previous, $errno, $errstr, $errfile, $errline) : false;
                });
            }

            add_filter('query', $redirect, PHP_INT_MAX);
            try {
                $handler
                    ->setSettings(array_merge([
                        'key_store'       => 'db',
                        'api_key'         => 'suite-key',
                        'sender_email'    => 'sender@example.test',
                        'sender_name'     => '',
                        'force_from_name' => 'no',
                    ], $spec['settings']))
                    ->setPhpMailer($mailer)
                    ->send();
            } catch (\Throwable $e) {
                // A Throwable here is the defect under test (a ValueError from realpath()).
                $failure = get_class($e) . ': ' . preg_replace('/[^\x20-\x7E]/', '?', substr($e->getMessage(), 0, 50));
            } finally {
                remove_filter('query', $redirect, PHP_INT_MAX);
                FsmtpTest::interceptHttp();
                if (!empty($spec['knownWarning'])) {
                    restore_error_handler();
                }
            }

            FsmtpTest::knownFailure(
                $knownSeen,
                'PepiPost getBody() reads PHPMailer::$contentType, which does not exist (app/Services/Mailer/Providers/PepiPost/Handler.php:160); see tests/FIX-PLAN.md.'
            );

            if ($handler instanceof FsmtpSuiteToSendAttachmentProbe && $handler->capturedBody !== null) {
                $request = ['body' => $handler->capturedBody, 'headers' => []];
            }

            return [
                'failure' => $failure,
                'rows'    => $request === null ? null : $spec['rows']($request),
            ];
        };

        foreach ($providers as $label => $spec) {
            // PepiPost, Brevo and Mailgun payloads carry no type; they are compared on name and bytes.
            $pdf = function ($name) use ($binary, $spec) {
                return [$name, $spec['typed'] ? 'application/pdf' : null, md5($binary)];
            };

            FsmtpTest::case($label . ': a file path arrives with its name, type and bytes', function () use ($apiSend, $spec, $pdf, $pdfPath) {
                $sent = $apiSend($spec, function ($mailer) use ($pdfPath) {
                    $mailer->addAttachment($pdfPath, 'Invoice 1001.pdf');
                });
                FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
                FsmtpTest::assertSame([$pdf('Invoice 1001.pdf')], $sent['rows'], 'attachments in the request');
            });

            FsmtpTest::case($label . ': in-memory data with NUL bytes arrives instead of escaping wp_mail()', function () use ($apiSend, $spec, $pdf, $binary) {
                // On 2.4.1 this threw an uncaught ValueError on PHP 8 (realpath() on the
                // data) and was silently dropped on PHP 7.4.
                $sent = $apiSend($spec, function ($mailer) use ($binary) {
                    $mailer->addStringAttachment($binary, 'invoice.pdf', 'base64', 'application/pdf');
                });
                FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
                FsmtpTest::assertSame([$pdf('invoice.pdf')], $sent['rows'], 'attachments in the request');
            });

            FsmtpTest::case($label . ': in-memory data without a name arrives as attachment.pdf', function () use ($apiSend, $spec, $pdf, $binary) {
                $sent = $apiSend($spec, function ($mailer) use ($binary) {
                    $mailer->addStringAttachment($binary, '', 'base64', 'application/pdf');
                });
                FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
                FsmtpTest::assertSame([$pdf('attachment.pdf')], $sent['rows'], 'attachments in the request');
            });

            FsmtpTest::case($label . ': a path without an extension takes its type from its name', function () use ($apiSend, $spec, $pdf, $binary) {
                $bare = tempnam(sys_get_temp_dir(), 'fsmtp-suite-');
                file_put_contents($bare, $binary);
                try {
                    $sent = $apiSend($spec, function ($mailer) use ($bare) {
                        $mailer->addAttachment($bare, 'ticket.pdf');
                    });
                } finally {
                    unlink($bare);
                }
                FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
                FsmtpTest::assertSame([$pdf('ticket.pdf')], $sent['rows'], 'attachments in the request');
            });

            FsmtpTest::case($label . ': a file named without an extension keeps its name and the path\'s type', function () use ($apiSend, $spec, $pdf, $pdfPath) {
                // wp_mail(..., ['Invoice' => $path]). Brevo's allow-list used to read
                // the path's extension, so this was sent there before the fix too.
                $sent = $apiSend($spec, function ($mailer) use ($pdfPath) {
                    $mailer->addAttachment($pdfPath, 'Invoice');
                });
                FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
                FsmtpTest::assertSame([$pdf('Invoice')], $sent['rows'], 'attachments in the request');
            });

            FsmtpTest::case($label . ': a blocked file is skipped and the email still goes', function () use ($apiSend, $spec, $pdf, $pdfPath, $otherPath) {
                // wp-config.php alone would not reach the blocklist on Brevo, whose
                // extension allow-list skips .php first; a blocked .pdf does.
                $blockOther = function ($paths) use ($otherPath) {
                    $paths[] = realpath($otherPath);
                    return $paths;
                };
                add_filter('fluentsmtp_attachment_blocked_paths', $blockOther);
                try {
                    $sent = $apiSend($spec, function ($mailer) use ($pdfPath, $otherPath) {
                        $mailer->addAttachment(ABSPATH . 'wp-config.php');
                        $mailer->addAttachment($otherPath, 'Blocked.pdf');
                        $mailer->addAttachment($pdfPath, 'Invoice 1001.pdf');
                    });
                } finally {
                    remove_filter('fluentsmtp_attachment_blocked_paths', $blockOther);
                }
                FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
                FsmtpTest::assertSame([$pdf('Invoice 1001.pdf')], $sent['rows'], 'attachments in the request');
            });

            FsmtpTest::case($label . ': a file and in-memory data arrive together', function () use ($apiSend, $spec, $pdf, $pdfPath, $binary) {
                $sent = $apiSend($spec, function ($mailer) use ($pdfPath, $binary) {
                    $mailer->addAttachment($pdfPath, 'Invoice 1001.pdf');
                    $mailer->addStringAttachment($binary, 'invoice.pdf', 'base64', 'application/pdf');
                });
                FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
                FsmtpTest::assertSame([$pdf('Invoice 1001.pdf'), $pdf('invoice.pdf')], $sent['rows'], 'attachments in the request');
            });
        }

        FsmtpTest::case('SendInBlue: unnamed in-memory data with no known extension is skipped, not read as a path', function () use ($apiSend, $providers, $binary) {
            // Delivered as "attachment", which has no extension. The path fallback
            // is for files only: here the data would supply its own ".pdf". The
            // named file after it shows the skip is not an empty request.
            $bytes = "%PDF-1.4\n\x00\x01\x02 see invoice.pdf";
            $sent = $apiSend($providers['SendInBlue'], function ($mailer) use ($bytes, $binary) {
                $mailer->addStringAttachment($bytes, '', 'base64', 'application/octet-stream');
                $mailer->addStringAttachment($binary, 'invoice.pdf', 'base64', 'application/pdf');
            });
            FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
            FsmtpTest::assertSame([['invoice.pdf', null, md5($binary)]], $sent['rows'], 'attachments in the request');
        });

        FsmtpTest::case('SendInBlue: an upper-case extension passes its allow-list', function () use ($apiSend, $providers, $pdfPath, $binary) {
            $sent = $apiSend($providers['SendInBlue'], function ($mailer) use ($pdfPath) {
                $mailer->addAttachment($pdfPath, 'Invoice.PDF');
            });
            FsmtpTest::assertSame(null, $sent['failure'], 'send failure');
            FsmtpTest::assertSame([['Invoice.PDF', null, md5($binary)]], $sent['rows'], 'attachments in the request');
        });

        FsmtpTest::case('wp_mail() hands the handler the attachment records the tests build', function () use ($pdfPath, $binary) {
            FsmtpTest::assertMailSimulationActive();

            $table = FsmtpFactory::emailLogTable();
            $redirect = FsmtpFactory::productionLogTableRedirect($table);
            $seen = null;
            $addInMemory = function ($phpmailer) use ($binary) {
                $phpmailer->addStringAttachment($binary, 'from-hook.pdf', 'base64', 'application/pdf');
            };
            $capture = function ($phpmailer) use (&$seen) {
                $seen = $phpmailer->getAttachments();
            };

            add_action('phpmailer_init', $addInMemory);
            add_action('phpmailer_init', $capture, PHP_INT_MAX);
            add_filter('query', $redirect, PHP_INT_MAX);
            try {
                wp_mail('to@example.test', 'attachments suite', 'body', [], ['Invoice 1001.pdf' => $pdfPath]);
            } finally {
                remove_filter('query', $redirect, PHP_INT_MAX);
                remove_action('phpmailer_init', $capture, PHP_INT_MAX);
                remove_action('phpmailer_init', $addInMemory);
            }

            FsmtpTest::assert(is_array($seen) && count($seen) === 2, 'expected two attachment records');
            $shape = function ($row) {
                return [
                    $row[5] ? 'in-memory:' . md5($row[0]) : basename($row[0]),
                    $row[2],
                    $row[4],
                    $row[5],
                    $row[6],
                ];
            };
            FsmtpTest::assertSame(['suite-document.pdf', 'Invoice 1001.pdf', 'application/pdf', false, 'attachment'], $shape($seen[0]), 'path record');
            FsmtpTest::assertSame(['in-memory:' . md5($binary), 'from-hook.pdf', 'application/pdf', true, 'attachment'], $shape($seen[1]), 'in-memory record');
        });
    } finally {
        $removeFixtures();
    }
};
