<?php

use FluentMail\App\Models\Logger;
use FluentMail\App\Services\Mailer\BaseHandler;

return function () {
    /*
     * A resend rebuilds the email from its log row. Files still on disk go out
     * again with their original name, type and inline disposition; data a
     * plugin built in memory and files that are gone cannot be included, and
     * the resend reports them instead of saying nothing. The log itself no
     * longer stores in-memory data.
     */
    FsmtpTest::requirePhpMailer();

    $dir = trailingslashit(wp_upload_dir()['basedir']) . FsmtpTest::uniq('fsmtp-resend-attachments');
    wp_mkdir_p($dir);
    $pdfPath = $dir . '/report.pdf';
    $pngPath = $dir . '/logo.png';
    file_put_contents($pdfPath, "%PDF-1.4\n%%EOF");
    file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    $inMemory = "%PDF-1.4\n\0\0 invoice\n%%EOF";

    /* The attachment records PHPMailer itself builds, as a send would log them. */
    $records = function (callable $add) {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $add($mailer);
        return $mailer->getAttachments();
    };

    $allKinds = function ($mailer) use ($pdfPath, $pngPath, $inMemory, $dir) {
        $mailer->addAttachment($pdfPath, 'Quarterly Report.pdf', 'base64', 'application/pdf');
        $mailer->addEmbeddedImage($pngPath, 'logo-cid', 'logo.png');
        $mailer->addStringAttachment($inMemory, 'invoice.pdf', 'base64', 'application/pdf');
        $gone = $dir . '/deleted-later.pdf';
        file_put_contents($gone, '%PDF-1.4');
        $mailer->addAttachment($gone);
        unlink($gone);
    };

    /* Resend a log row from an isolated table; return what PHPMailer was given and the result. */
    $resend = function (array $attachments, $type = 'resend') {
        $table = FsmtpFactory::emailLogTable();
        $GLOBALS['fsmtp_resend_subject'] = FsmtpTest::uniq('resend');
        $id = FsmtpFactory::insertLog($table, [
            'to'          => serialize([['email' => 'to@example.test']]),
            'from'        => 'Suite Sender <sender@example.test>',
            'subject'     => $GLOBALS['fsmtp_resend_subject'],
            'body'        => '<p>body <img src="cid:logo-cid"></p>',
            'headers'     => serialize(['content-type' => 'text/html']),
            'attachments' => serialize(BaseHandler::attachmentsForLog($attachments)),
            'extra'       => serialize([]),
        ]);

        $seen = null;
        $extra = null;
        $listener = function ($phpmailer) use (&$seen, &$extra) {
            if ($phpmailer->Subject !== $GLOBALS['fsmtp_resend_subject']) {
                return;
            }
            $seen = array_map(function ($a) {
                return ['path' => $a[0], 'name' => $a[2], 'type' => $a[4], 'disposition' => $a[6], 'cid' => $a[7]];
            }, $phpmailer->getAttachments());
            $copy = clone $phpmailer;
            $copy->preSend();
            $extra = [
                'headers' => array_column($phpmailer->getCustomHeaders(), 0),
                'mime'    => $copy->getSentMIMEMessage(),
            ];
        };
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        // The resend restores attachments late on phpmailer_init; read after it.
        add_action('phpmailer_init', $listener, PHP_INT_MAX);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            $result = FsmtpFactory::loggerForTable($table)->resendEmailFromLog($id, $type);
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            remove_action('phpmailer_init', $listener, PHP_INT_MAX);
        }

        FsmtpTest::assert($seen !== null, 'phpmailer_init did not fire during the resend');
        return ['seen' => $seen, 'result' => $result, 'headers' => $extra ? $extra['headers'] : null, 'mime' => $extra ? $extra['mime'] : null];
    };

    try {
        // Runs first: every resend defines FLUENTMAIL_LOG_OFF for the rest of the request.
        FsmtpTest::case('a real send logs an in-memory attachment without its data', function () use ($pdfPath, $inMemory) {
            if (defined('FLUENTMAIL_LOG_OFF')) {
                FsmtpTest::skip('an earlier resend in this process turned logging off (FLUENTMAIL_LOG_OFF)');
                return;
            }
            global $wpdb;
            $table = FsmtpFactory::emailLogTable();
            $redirect = FsmtpFactory::productionLogTableRedirect($table);
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->setFrom('sender@example.test');
            $mailer->addAddress('to@example.test');
            $mailer->Subject = 'logged';
            $mailer->Body = 'body';
            $mailer->addAttachment($pdfPath);
            $mailer->addStringAttachment($inMemory, 'invoice.pdf', 'base64', 'application/pdf');

            FsmtpTest::interceptHttp(function ($url) {
                if (strpos($url, 'https://api.sendgrid.com/') !== 0) {
                    return null;
                }
                return ['headers' => [], 'body' => '', 'response' => ['code' => 202, 'message' => 'Accepted'], 'cookies' => [], 'filename' => null];
            });
            add_filter('query', $redirect, PHP_INT_MAX);
            add_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
            try {
                (new \FluentMail\App\Services\Mailer\Providers\SendGrid\Handler())
                    ->setSettings(['key_store' => 'db', 'api_key' => 'k', 'sender_email' => 'sender@example.test', 'sender_name' => '', 'force_from_name' => 'no', 'provider' => 'sendgrid'])
                    ->setPhpMailer($mailer)
                    ->send();
            } finally {
                remove_filter('query', $redirect, PHP_INT_MAX);
                remove_filter('fluentmail_will_log_email', '__return_true', PHP_INT_MAX);
                FsmtpTest::interceptHttp();
            }

            $stored = unserialize($wpdb->get_var("SELECT attachments FROM `{$table}` ORDER BY id DESC LIMIT 1"), ['allowed_classes' => false]);
            FsmtpTest::assertSame($pdfPath, $stored[0][0], 'file path stored');
            FsmtpTest::assertSame('', $stored[1][0], 'in-memory data not stored');
            FsmtpTest::assertSame(strlen($inMemory), (int) $stored[1][8], 'in-memory size stored');
        });

        FsmtpTest::case('the log keeps file paths but not the data of in-memory attachments', function () use ($records, $allKinds, $inMemory, $pdfPath) {
            $logged = BaseHandler::attachmentsForLog($records($allKinds));

            FsmtpTest::assertSame($pdfPath, $logged[0][0], 'file path kept');
            FsmtpTest::assertSame('', $logged[2][0], 'in-memory data not stored');
            FsmtpTest::assertSame(true, (bool)$logged[2][5], 'still marked as in-memory');
            FsmtpTest::assertSame(strlen($inMemory), $logged[2][8], 'its size is kept');
            FsmtpTest::assertSame('invoice.pdf', $logged[2][2], 'its name is kept');
        });

        FsmtpTest::case('each logged attachment is described with whether a resend can include it', function () use ($records, $allKinds) {
            $list = Logger::describeAttachments(BaseHandler::attachmentsForLog($records($allKinds)));
            $summary = array_map(function ($item) {
                return [$item['name'], $item['status'], $item['inline']];
            }, $list);

            FsmtpTest::assertSame([
                ['Quarterly Report.pdf', 'ok', false],
                ['logo.png', 'ok', true],
                ['invoice.pdf', 'in_memory', false],
                ['deleted-later.pdf', 'missing', false],
            ], $summary, 'attachment list');
        });

        FsmtpTest::case('rows logged before this change are described too, and their in-memory data is not sent to the browser', function () use ($pdfPath, $inMemory) {
            // Old rows stored the in-memory data at index 0; very old ones a bare path.
            $legacy = [
                $pdfPath,
                [$inMemory, 'invoice.pdf', 'invoice.pdf', 'base64', 'application/pdf', true, 'attachment', 0],
            ];
            $summary = array_map(function ($item) {
                return [$item['name'], $item['status']];
            }, Logger::describeAttachments($legacy));
            FsmtpTest::assertSame([['report.pdf', 'ok'], ['invoice.pdf', 'in_memory']], $summary, 'legacy list');
            FsmtpTest::assertSame('', BaseHandler::attachmentsForLog($legacy)[1][0], 'legacy in-memory data stripped for display');
        });

        FsmtpTest::case('a resend includes every file still on disk, with its original name, type and inline image', function () use ($resend, $records, $allKinds, $pdfPath, $pngPath) {
            FsmtpTest::assertMailSimulationActive();
            $sent = $resend($records($allKinds));

            FsmtpTest::assertSame([
                // PHPMailer gives an ordinary attachment its name as the content id.
                ['path' => $pdfPath, 'name' => 'Quarterly Report.pdf', 'type' => 'application/pdf', 'disposition' => 'attachment', 'cid' => 'Quarterly Report.pdf'],
                ['path' => $pngPath, 'name' => 'logo.png', 'type' => 'image/png', 'disposition' => 'inline', 'cid' => 'logo-cid'],
            ], $sent['seen'], 'attachments PHPMailer was given');
        });

        FsmtpTest::case('a resend reports the attachments it could not include', function () use ($resend, $records, $allKinds) {
            FsmtpTest::assertMailSimulationActive();
            $sent = $resend($records($allKinds));

            FsmtpTest::assert(is_array($sent['result']), 'resend returned the log row');
            FsmtpTest::assertSame([
                ['name' => 'invoice.pdf', 'status' => 'in_memory'],
                ['name' => 'deleted-later.pdf', 'status' => 'missing'],
            ], $sent['result']['skipped_attachments'], 'skipped attachments');
        });

        FsmtpTest::case('a resend with every attachment available reports nothing skipped', function () use ($resend, $records, $pdfPath) {
            FsmtpTest::assertMailSimulationActive();
            $sent = $resend($records(function ($m) use ($pdfPath) {
                $m->addAttachment($pdfPath);
            }), 'retry');

            FsmtpTest::assertSame([], $sent['result']['skipped_attachments'], 'nothing skipped');
            FsmtpTest::assertSame(1, count($sent['seen']), 'the file was attached');
        });

        FsmtpTest::case('the resend route names the attachments it left out', function () use ($records, $allKinds) {
            FsmtpTest::assertMailSimulationActive();
            $table = FsmtpFactory::emailLogTable();
            $id = FsmtpFactory::insertLog($table, [
                'to'          => serialize([['email' => 'to@example.test']]),
                'from'        => 'Suite Sender <sender@example.test>',
                'body'        => 'body',
                'headers'     => serialize(['content-type' => 'text/plain']),
                'attachments' => serialize(BaseHandler::attachmentsForLog($records($allKinds))),
                'extra'       => serialize([]),
            ]);
            $redirect = FsmtpFactory::productionLogTableRedirect($table);

            add_filter('query', $redirect, PHP_INT_MAX);
            try {
                $single = FsmtpTest::ajax('POST', '/logs/retry', ['id' => $id, 'type' => 'resend']);
                $bulk = FsmtpTest::ajax('POST', '/logs/retry-bulk', ['log_ids' => [$id]]);
            } finally {
                remove_filter('query', $redirect, PHP_INT_MAX);
            }

            FsmtpTest::assertAjaxHealthy($single, 'single resend');
            $message = $single['data']['data']['message'];
            FsmtpTest::assert(strpos($message, 'invoice.pdf (created in memory by the sending plugin, not stored)') !== false, 'message names the in-memory file: ' . $message);
            FsmtpTest::assert(strpos($message, 'deleted-later.pdf (file no longer exists or can\'t be read)') !== false, 'message names the missing file: ' . $message);
            FsmtpTest::assertSame(2, count($single['data']['data']['skipped_attachments']), 'skipped list in the response');

            FsmtpTest::assertAjaxHealthy($bulk, 'bulk resend');
            FsmtpTest::assert(strpos($bulk['data']['data']['message'], '1 was sent without one or more attachments') !== false, 'bulk message: ' . $bulk['data']['data']['message']);
        });

        FsmtpTest::case('each status stays with its own record, so an unchecked path is never attached', function () use ($resend, $pdfPath) {
            FsmtpTest::assertMailSimulationActive();
            // A tampered or very old row: a non-attachment entry first, then a blocked system file.
            $sent = $resend([null, '/etc/hosts', [$pdfPath, 'report.pdf', 'report.pdf', 'base64', 'application/pdf', false, 'attachment', 'report.pdf']]);

            FsmtpTest::assertSame([$pdfPath], array_column($sent['seen'], 'path'), 'only the readable upload is attached');
            FsmtpTest::assertSame([['name' => 'hosts', 'status' => 'missing']], $sent['result']['skipped_attachments'], 'the blocked file is reported, not attached');
        });

        FsmtpTest::case('wp_mail() receives the files under their logged names and the inline image as an embed', function () use ($resend, $records, $allKinds, $pdfPath, $pngPath) {
            FsmtpTest::assertMailSimulationActive();
            $given = null;
            // pre_wp_mail sees what queue plugins and other transports would get.
            $listener = function ($return, $atts) use (&$given) {
                $given = ['attachments' => $atts['attachments'], 'embeds' => isset($atts['embeds']) ? $atts['embeds'] : null];
                return $return;
            };
            add_filter('pre_wp_mail', $listener, 10, 2);
            try {
                $resend($records($allKinds));
            } finally {
                remove_filter('pre_wp_mail', $listener, 10);
            }
            FsmtpTest::assertSame(['Quarterly Report.pdf' => $pdfPath], $given['attachments'], 'attachments argument');
            FsmtpTest::assertSame(['logo-cid' => $pngPath], $given['embeds'], 'embeds argument');
        });

        FsmtpTest::case('a notification sent from a wp_mail filter during a resend does not get its attachments', function () use ($resend, $records, $allKinds) {
            FsmtpTest::assertMailSimulationActive();
            $nested = null;
            $once = false;
            $sendNested = function ($atts) use (&$once) {
                if (!$once) {
                    $once = true;
                    wp_mail('nested@example.test', 'nested', 'nested body');
                }
                return $atts;
            };
            $watch = function ($phpmailer) use (&$nested) {
                if ($phpmailer->Subject === 'nested') {
                    $nested = count($phpmailer->getAttachments());
                }
            };
            add_filter('wp_mail', $sendNested);
            add_action('phpmailer_init', $watch, 20);
            try {
                $sent = $resend($records($allKinds));
            } finally {
                remove_filter('wp_mail', $sendNested);
                remove_action('phpmailer_init', $watch, 20);
            }
            FsmtpTest::assertSame(0, $nested, 'the nested email carries no attachments');
            FsmtpTest::assertSame(2, count($sent['seen']), 'the resend still carries its two files');
            FsmtpTest::assertSame(['Quarterly Report.pdf', 'logo.png'], array_column($sent['seen'], 'name'), 'with their original names');
        });

        FsmtpTest::case('an attachment another plugin adds during the resend is kept as it was', function () use ($resend, $records, $pdfPath) {
            FsmtpTest::assertMailSimulationActive();
            $addTerms = function ($phpmailer) {
                $phpmailer->addStringAttachment('terms', 'terms.txt', 'base64', 'text/plain');
            };
            add_action('phpmailer_init', $addTerms, 5);
            try {
                $sent = $resend($records(function ($m) use ($pdfPath) {
                    $m->addAttachment($pdfPath, 'Invoice.pdf');
                }));
            } finally {
                remove_action('phpmailer_init', $addTerms, 5);
            }
            FsmtpTest::assertSame(['Invoice.pdf', 'terms.txt'], array_column($sent['seen'], 'name'), 'both attachments');
        });

        FsmtpTest::case('an embedded image another plugin adds before the resend runs keeps its content id', function () use ($resend, $records, $pdfPath) {
            FsmtpTest::assertMailSimulationActive();
            $addLogo = function ($phpmailer) {
                $phpmailer->addStringEmbeddedImage('logo-bytes', 'plugin-logo', 'logo.png', 'base64', 'image/png');
            };
            add_action('phpmailer_init', $addLogo, 0);
            try {
                $sent = $resend($records(function ($m) use ($pdfPath) {
                    $m->addAttachment($pdfPath, 'Invoice.pdf');
                }));
            } finally {
                remove_action('phpmailer_init', $addLogo, 0);
            }
            $byName = array_column($sent['seen'], null, 'name');
            FsmtpTest::assertSame('inline', $byName['logo.png']['disposition'], 'plugin logo still inline');
            FsmtpTest::assertSame('plugin-logo', $byName['logo.png']['cid'], 'plugin logo keeps its cid');
            FsmtpTest::assertSame('attachment', $byName['Invoice.pdf']['disposition'], 'resent file restored');
        });

        FsmtpTest::case('a nested email carrying the same files does not take the restore from the resend', function () use ($resend, $records, $pdfPath) {
            FsmtpTest::assertMailSimulationActive();
            $once = false;
            $nestedNames = null;
            $sendNested = function ($atts) use (&$once, $pdfPath) {
                if (!$once) {
                    $once = true;
                    wp_mail('nested@example.test', 'nested', 'nested body', '', [$pdfPath]);
                }
                return $atts;
            };
            $watch = function ($phpmailer) use (&$nestedNames) {
                if ($phpmailer->Subject === 'nested') {
                    $nestedNames = array_column($phpmailer->getAttachments(), 2);
                }
            };
            add_filter('wp_mail', $sendNested);
            add_action('phpmailer_init', $watch, 20);
            try {
                $sent = $resend($records(function ($m) use ($pdfPath) {
                    $m->addAttachment($pdfPath, 'Quarterly Report.pdf');
                }));
            } finally {
                remove_filter('wp_mail', $sendNested);
                remove_action('phpmailer_init', $watch, 20);
            }
            FsmtpTest::assert($nestedNames !== null, 'the nested email was sent');
            FsmtpTest::assertSame(['Quarterly Report.pdf'], array_column($sent['seen'], 'name'), 'the resend still has its logged name');
            FsmtpTest::assertSame([], $sent['result']['skipped_attachments'], 'nothing reported as skipped');
        });

        FsmtpTest::case('the same file attached twice under two names is resent with both names', function () use ($resend, $records, $pdfPath) {
            FsmtpTest::assertMailSimulationActive();
            $sent = $resend($records(function ($m) use ($pdfPath) {
                $m->addAttachment($pdfPath, 'Copy A.pdf');
                $m->addAttachment($pdfPath, 'Copy B.pdf');
            }));
            FsmtpTest::assertSame(['Copy A.pdf', 'Copy B.pdf'], array_column($sent['seen'], 'name'), 'both names in order');
            FsmtpTest::assertSame(true, $sent['result']['resend_succeeded'], 'the send result is returned');
        });

        FsmtpTest::case('a pre_wp_mail transport that takes over the send gets no false missing-file warning', function () use ($records, $pdfPath) {
            $table = FsmtpFactory::emailLogTable();
            $id = FsmtpFactory::insertLog($table, [
                'to'          => serialize([['email' => 'to@example.test']]),
                'from'        => 'Suite Sender <sender@example.test>',
                'body'        => 'body',
                'headers'     => serialize(['content-type' => 'text/plain']),
                'attachments' => serialize(BaseHandler::attachmentsForLog($records(function ($m) use ($pdfPath) {
                    $m->addAttachment($pdfPath);
                }))),
                'extra'       => serialize([]),
            ]);
            $queue = function () {
                return true; // a queue plugin accepted the email; phpmailer_init never runs
            };
            $redirect = FsmtpFactory::productionLogTableRedirect($table);
            add_filter('pre_wp_mail', $queue);
            add_filter('query', $redirect, PHP_INT_MAX);
            try {
                $result = FsmtpFactory::loggerForTable($table)->resendEmailFromLog($id, 'resend');
            } finally {
                remove_filter('query', $redirect, PHP_INT_MAX);
                remove_filter('pre_wp_mail', $queue);
            }
            FsmtpTest::assertSame([], $result['skipped_attachments'], 'no false warning');
        });

        FsmtpTest::case('the built message embeds the logo and keeps the file name', function () use ($resend, $records, $allKinds) {
            FsmtpTest::assertMailSimulationActive();
            $sent = $resend($records($allKinds));

            FsmtpTest::assert(stripos($sent['mime'], 'multipart/related') !== false, 'message is multipart/related');
            FsmtpTest::assert(strpos($sent['mime'], 'Content-ID: <logo-cid>') !== false, 'logo part carries its Content-ID');
            FsmtpTest::assert(strpos($sent['mime'], 'filename="Quarterly Report.pdf"') !== false, 'report keeps its name in the MIME');
        });

        FsmtpTest::case('an inline image whose content id is "0" is restored inline', function () use ($resend, $records, $pngPath) {
            FsmtpTest::assertMailSimulationActive();
            $sent = $resend($records(function ($m) use ($pngPath) {
                $m->addEmbeddedImage($pngPath, '0', 'logo.png');
            }));
            FsmtpTest::assertSame([['inline', '0']], array_map(function ($a) {
                return [$a['disposition'], (string) $a['cid']];
            }, $sent['seen']), 'inline with cid 0');
        });

        FsmtpTest::case('a nested notification is never mistaken for the resend when a queue plugin takes the resend over', function () use ($records, $pdfPath) {
            $table = FsmtpFactory::emailLogTable();
            $subject = FsmtpTest::uniq('queued');
            $id = FsmtpFactory::insertLog($table, [
                'to'          => serialize([['email' => 'to@example.test']]),
                'from'        => 'Suite Sender <sender@example.test>',
                'subject'     => $subject,
                'body'        => 'body',
                'headers'     => serialize(['content-type' => 'text/plain']),
                'attachments' => serialize(BaseHandler::attachmentsForLog($records(function ($m) use ($pdfPath) {
                    $m->addAttachment($pdfPath);
                }))),
                'extra'       => serialize([]),
            ]);
            $once = false;
            // A filter sends a notification (it reaches phpmailer_init) ...
            $notify = function ($atts) use (&$once, $subject) {
                if (!$once && $atts['subject'] === $subject) {
                    $once = true;
                    wp_mail('admin@example.test', 'notice', 'a resend happened');
                }
                return $atts;
            };
            // ... and a queue plugin takes the resend itself, so it never does.
            $queue = function ($return, $atts) use ($subject) {
                return $atts['subject'] === $subject ? true : $return;
            };
            $redirect = FsmtpFactory::productionLogTableRedirect($table);
            add_filter('wp_mail', $notify);
            add_filter('pre_wp_mail', $queue, 10, 2);
            add_filter('query', $redirect, PHP_INT_MAX);
            try {
                $result = FsmtpFactory::loggerForTable($table)->resendEmailFromLog($id, 'resend');
            } finally {
                remove_filter('query', $redirect, PHP_INT_MAX);
                remove_filter('pre_wp_mail', $queue, 10);
                remove_filter('wp_mail', $notify);
            }
            FsmtpTest::assert($once, 'the notification was sent');
            FsmtpTest::assertSame([], $result['skipped_attachments'], 'no false missing-file warning');
        });

        FsmtpTest::case('a notification sent from pre_wp_mail during the resend is not taken for the resend', function () use ($resend, $records, $pdfPath) {
            FsmtpTest::assertMailSimulationActive();
            $once = false;
            // Runs after the wp_mail filters, before the resend reaches phpmailer_init, and lets it continue.
            $notifyFirst = function ($return, $atts) use (&$once) {
                if (!$once && $atts['subject'] === $GLOBALS['fsmtp_resend_subject']) {
                    $once = true;
                    wp_mail('audit@example.test', 'audit notice', 'about to resend');
                }
                return $return;
            };
            add_filter('pre_wp_mail', $notifyFirst, 10, 2);
            try {
                $sent = $resend($records(function ($m) use ($pdfPath) {
                    $m->addAttachment($pdfPath, 'Invoice.pdf');
                }));
            } finally {
                remove_filter('pre_wp_mail', $notifyFirst, 10);
            }
            FsmtpTest::assert($once, 'the notification was sent');
            FsmtpTest::assertSame(['Invoice.pdf'], array_column($sent['seen'], 'name'), 'the resend is still restored');
        });

        FsmtpTest::case('a file a wp_mail filter removes is left out, and the rest keep their logged names', function () use ($resend, $records, $pdfPath) {
            FsmtpTest::assertMailSimulationActive();
            $drop = function ($atts) {
                unset($atts['attachments']['Copy B.pdf']);
                return $atts;
            };
            add_filter('wp_mail', $drop);
            try {
                $sent = $resend($records(function ($m) use ($pdfPath) {
                    $m->addAttachment($pdfPath, 'Copy A.pdf');
                    $m->addAttachment($pdfPath, 'Copy B.pdf');
                }));
            } finally {
                remove_filter('wp_mail', $drop);
            }
            FsmtpTest::assertSame(['Copy A.pdf'], array_column($sent['seen'], 'name'), 'the kept file keeps its name');
        });

        FsmtpTest::case('two files with the same logged name are both resent', function () use ($resend, $records, $pdfPath, $dir) {
            FsmtpTest::assertMailSimulationActive();
            $other = $dir . '/other.pdf';
            file_put_contents($other, '%PDF-1.4 other');
            $sent = $resend($records(function ($m) use ($pdfPath, $other) {
                $m->addAttachment($pdfPath, 'Invoice.pdf');
                $m->addAttachment($other, 'Invoice.pdf');
            }));
            FsmtpTest::assertSame([$pdfPath, $other], array_column($sent['seen'], 'path'), 'both files go out');
            FsmtpTest::assertSame('Invoice.pdf', $sent['seen'][0]['name'], 'the first keeps the logged name');
        });

        FsmtpTest::case('formatting a stored row again keeps the size of an in-memory attachment', function () use ($records, $inMemory) {
            $once = BaseHandler::attachmentsForLog($records(function ($m) use ($inMemory) {
                $m->addStringAttachment($inMemory, 'invoice.pdf');
            }));
            $twice = BaseHandler::attachmentsForLog($once);
            FsmtpTest::assertSame(strlen($inMemory), $twice[0][8], 'size kept');
        });

        FsmtpTest::case('log rows sent to the admin UI carry their attachment list and no in-memory data', function () use ($records, $allKinds) {
            // An old row, with the in-memory data still at index 0.
            $row = [
                'id'          => 1,
                'retries'     => 0,
                'from'        => 'sender@example.test',
                'subject'     => 'subject',
                'to'          => serialize([['email' => 'to@example.test']]),
                'attachments' => serialize($records($allKinds)),
            ];
            $format = new ReflectionMethod(Logger::class, 'formatResult');
            $format->setAccessible(true);
            $log = $format->invoke(new Logger(), [$row])[0];

            FsmtpTest::assertSame(4, count($log['attachment_list']), 'attachment list');
            FsmtpTest::assertSame('in_memory', $log['attachment_list'][2]['status'], 'in-memory status');
            FsmtpTest::assertSame('', $log['attachments'][2][0], 'no in-memory data in the response');
        });
    } finally {
        foreach ((array) glob($dir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
};
