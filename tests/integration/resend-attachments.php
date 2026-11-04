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
        $id = FsmtpFactory::insertLog($table, [
            'to'          => serialize([['email' => 'to@example.test']]),
            'from'        => 'Suite Sender <sender@example.test>',
            'body'        => '<p>body <img src="cid:logo-cid"></p>',
            'headers'     => serialize(['content-type' => 'text/html']),
            'attachments' => serialize(BaseHandler::attachmentsForLog($attachments)),
            'extra'       => serialize([]),
        ]);

        $seen = null;
        $listener = function ($phpmailer) use (&$seen) {
            $seen = array_map(function ($a) {
                return ['path' => $a[0], 'name' => $a[2], 'type' => $a[4], 'disposition' => $a[6], 'cid' => $a[7]];
            }, $phpmailer->getAttachments());
        };
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        add_action('phpmailer_init', $listener, 20);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            $result = FsmtpFactory::loggerForTable($table)->resendEmailFromLog($id, $type);
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            remove_action('phpmailer_init', $listener, 20);
        }

        FsmtpTest::assert($seen !== null, 'phpmailer_init did not fire during the resend');
        return ['seen' => $seen, 'result' => $result];
    };

    try {
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
            FsmtpTest::assert(strpos($message, 'deleted-later.pdf (file no longer exists)') !== false, 'message names the missing file: ' . $message);
            FsmtpTest::assertSame(2, count($single['data']['data']['skipped_attachments']), 'skipped list in the response');

            FsmtpTest::assertAjaxHealthy($bulk, 'bulk resend');
            FsmtpTest::assert(strpos($bulk['data']['data']['message'], '1 was sent without one or more attachments') !== false, 'bulk message: ' . $bulk['data']['data']['message']);
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
