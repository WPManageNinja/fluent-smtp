<?php

use FluentMail\App\Services\Mailer\FluentPHPMailer;
use FluentMail\App\Services\Mailer\Providers\SendGrid\Handler;
use PHPMailer\PHPMailer\PHPMailer;

return function () {
    // The harness forces simulated routing. Exercise the real primary provider
    // with intercepted HTTP, keeping the configured backup on the Simulator.
    foreach (['failed', 'sent_via_fallback', 'primary_success', 'invalid_backup', 'missing_log'] as $scenario) {
        FsmtpTest::case('managed mail outcome ' . $scenario, function () use ($scenario) {
            FsmtpTest::assertMailSimulationActive();
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            $mail = new PHPMailer(true);
            $mail->setFrom('primary@example.test');
            $mail->addAddress('recipient@example.test');
            $mail->Subject = FsmtpTest::uniq('outcome');
            $mail->Body = 'Fixture';
            $mailer = new class($mail) extends FluentPHPMailer {
                protected function sendPrimary()
                {
                    $provider = new Handler();
                    $provider->setSettings([
                        'provider' => 'sendgrid', 'key_store' => 'db', 'api_key' => 'fixture',
                        'sender_email' => 'primary@example.test', 'sender_name' => 'Fixture',
                    ]);
                    return $provider->setPhpMailer($this->phpMailer)->send();
                }
            };
            $settings = [
                'misc' => ['fallback_connection' => in_array($scenario, ['sent_via_fallback', 'invalid_backup'], true) ? 'backup' : ''],
                'connections' => ['backup' => ['provider_settings' => [
                    'sender_email' => $scenario === 'invalid_backup' ? 'invalid' : 'backup@example.test',
                ]]],
                'mappings' => [],
            ];
            $options = static function () use ($settings) { return $settings; };
            $throttle = static function () { return time(); };
            $events = [];
            $observer = static function ($outcome, $logId) use (&$events) {
                $events[] = [$outcome, is_int($logId) && $logId > 0 ? 'positive' : $logId];
            };
            add_filter('pre_option_fluentmail-settings', $options, PHP_INT_MAX);
            add_filter('pre_option__fsmtp_last_notification_sent', $throttle, PHP_INT_MAX);
            add_action('fluentmail_delivery_outcome', $observer, 10, 2);
            fluentMailGetSettings([], false);
            FsmtpTest::interceptHttp(static function () use ($scenario) {
                return ['headers' => [], 'response' => ['code' => $scenario === 'primary_success' ? 202 : 401], 'body' => '{}'];
            });
            global $wpdb;
            $logTable = $wpdb->prefix . 'fsmpt_email_logs';
            $absentTable = $wpdb->prefix . FsmtpTest::uniq('missing_outcome_log');
            $rejectLogInsert = static function ($sql) use ($logTable, $absentTable) {
                if (stripos(ltrim($sql), 'INSERT INTO') === 0 && strpos($sql, $logTable) !== false) {
                    return str_replace($logTable, $absentTable, $sql);
                }
                return $sql;
            };
            $wpdb->query('START TRANSACTION');
            $previousSuppression = $wpdb->suppress_errors();
            try {
                if ($scenario === 'missing_log') {
                    add_filter('query', $rejectLogInsert, PHP_INT_MAX);
                }
                try {
                    $sent = $mailer->send();
                } catch (\PHPMailer\PHPMailer\Exception $exception) {
                    $sent = false;
                }
                $succeeded = in_array($scenario, ['sent_via_fallback', 'primary_success'], true);
                $outcome = $succeeded ? 'sent_via_fallback' : 'failed';
                $expected = $scenario === 'primary_success' ? [] : [[$outcome, $scenario === 'missing_log' ? 0 : 'positive']];
                FsmtpTest::assertSame($succeeded, $sent, 'send result');
                FsmtpTest::assertSame($expected, $events, 'observable outcomes');
            } finally {
                remove_filter('query', $rejectLogInsert, PHP_INT_MAX);
                $wpdb->suppress_errors($previousSuppression);
                $wpdb->query('ROLLBACK');
                remove_action('fluentmail_delivery_outcome', $observer);
                remove_filter('pre_option_fluentmail-settings', $options, PHP_INT_MAX);
                remove_filter('pre_option__fsmtp_last_notification_sent', $throttle, PHP_INT_MAX);
                fluentMailGetSettings([], false);
                FsmtpTest::interceptHttp();
            }
        });
    }
};
