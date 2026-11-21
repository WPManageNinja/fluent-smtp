<?php

use FluentMail\App\Models\Logger;

return function () {
    FsmtpTest::requirePhpMailer();
    FsmtpTest::case('email body logging can be omitted per message before insertion', function () {
        global $wpdb;
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $marker = FsmtpTest::uniq('private-body');
        $row = [
            'subject'     => 'Private fixture',
            'body'        => $marker,
            'status'      => 'failed',
            'from'        => 'sender@example.test',
            'to'          => serialize([['email' => 'to@example.test']]),
            'response'    => serialize(['code' => 401, 'message' => $marker, 'errors' => ['detail' => $marker]]),
            'headers'     => serialize(['X-Test' => $marker]),
            'attachments' => serialize([[$marker]]),
            'extra'       => serialize(['provider' => 'sendgrid', 'send_time_ms' => 45, 'custom_headers' => [$marker]]),
        ];
        $calls = [];
        $policy = function ($store, $mail) use (&$calls) {
            $calls[] = $mail;
            return $store && $mail['subject'] !== 'Private fixture';
        };
        add_filter('query', $redirect, PHP_INT_MAX);
        add_filter('fluentmail_log_email_body', $policy, 10, 2);
        try {
            $logger = new Logger();
            $private = $logger->add($row);
            $row['subject'] = 'Normal fixture';
            $regular = $logger->add($row);
            $stored = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $private), ARRAY_A);
            FsmtpTest::assertSame('', $stored['body'], 'omitted body on insert');
            FsmtpTest::assert(strpos(wp_json_encode($stored), $marker) === false, 'provider echo or payload retained');
            FsmtpTest::assertSame(401, maybe_unserialize($stored['response'])['code'], 'kept failure code');
            FsmtpTest::assert(!empty(maybe_unserialize($stored['extra'])['body_omitted']), 'missing explicit omission marker');
            FsmtpTest::assertSame($marker, $logger->find($regular)['body'], 'unselected mail keeps its body');
            FsmtpTest::assertSame([$marker, $marker], array_column($calls, 'body'), 'policy sees original bodies');
            FsmtpTest::assertSame(['to@example.test'], $calls[0]['to'], 'primary recipient addresses');
            FsmtpTest::assertSame('failed', $calls[0]['status'], 'primary delivery status');
            FsmtpTest::assertSame('sendgrid', $calls[0]['provider'], 'primary provider');
            remove_filter('fluentmail_log_email_body', $policy, 10);
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->setFrom('backup@example.test');
            $mailer->addAddress('to@example.test');
            $mailer->Subject = 'Private fixture';
            $mailer->Body = $marker;
            $handler = (new \FluentMail\App\Services\Mailer\Providers\SendGrid\Handler())
                ->setSettings(['key_store' => 'db', 'api_key' => 'fixture', 'provider' => 'sendgrid', 'sender_email' => 'backup@example.test'])
                ->setPhpMailer($mailer);
            $handler->setRowId($private);
            FsmtpTest::interceptHttp(static function () {
                return ['headers' => [], 'body' => '', 'response' => ['code' => 202, 'message' => 'Accepted']];
            });
            $handler->send();
            $updated = $logger->find($private);
            FsmtpTest::assertSame('', $updated['body'], 'fallback after policy removal still omits body');
            FsmtpTest::assertSame('sent', $updated['status'], 'backup succeeded');
            FsmtpTest::assertSame(401, $updated['response']['code'], 'kept original failure code after fallback');
            FsmtpTest::assert(!empty($updated['extra']['body_omitted']), 'fallback retained omission marker');
            $blocked = false;
            try {
                $logger->resendEmailFromLog($private);
            } catch (\Exception $e) {
                $blocked = $e->getCode() === 409;
            }
            FsmtpTest::assert($blocked, 'refuse resend before calling wp_mail');
        } finally {
            FsmtpTest::interceptHttp();
            remove_filter('fluentmail_log_email_body', $policy, 10);
            remove_filter('query', $redirect, PHP_INT_MAX);
        }
    });

    FsmtpTest::case('a broken body policy keeps the body out of its log', function () {
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $broken = static function () {
            throw new \Error('Synthetic policy error');
        };
        add_filter('query', $redirect, PHP_INT_MAX);
        add_filter('fluentmail_log_email_body', $broken);
        try {
            $logger = new Logger();
            $id = $logger->add(['body' => 'Synthetic text', 'subject' => 'Broken callback', 'status' => 'sent']);
            $row = $logger->find($id);
            FsmtpTest::assertSame('', $row['body'], 'callback failure omits content');
            FsmtpTest::assert(!empty($row['extra']['body_omitted']), 'callback failure retains delivery record');
        } finally {
            remove_filter('fluentmail_log_email_body', $broken);
            remove_filter('query', $redirect, PHP_INT_MAX);
        }
    });

    FsmtpTest::case('bulk resend only sends retained messages', function () {
        // Resend disables logging for its request. Isolate that constant from other cases.
        $result = FsmtpTest::wpCli(['eval-file', __DIR__ . '/fixtures/log-body-resend.php'], [
            'FSMTP_SUITE_SETTINGS_B64' => base64_encode(wp_json_encode(['misc' => ['simulate_emails' => 'yes']])),
        ]);
        FsmtpTest::assertSame(0, $result['code'], $result['output']);
    });
};
