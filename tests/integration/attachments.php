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

return function () {
    /*
     * Attachments reach the recipient once, with their name, type and bytes
     * intact, however the sender added them: a file path, data held in memory
     * (addStringAttachment(), as Germanized and the #437 reporter's invoice
     * plugin do), or an inline image. The cases drive the real handlers with
     * PHPMailer's real MIME builder and compare decoded parts by hash.
     */

    // Every byte value, so an encoding that mangles any byte shows up as a hash mismatch.
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
            // A phpmailer_init listener may clear the list and add its own. The
            // handler must send exactly the object's final list.
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
