<?php

use FluentMail\App\Services\Mailer\Providers\SendGrid\Handler;

if (!isset($args[0]) || $args[0] !== 'outcome-child') {
    return static function () {
        // A rejected provider can unwind through WP_Hook. Give each send its
        // own process so unfinished filter frames cannot affect later tests.
        foreach (['failed', 'sent_via_fallback', 'primary_success', 'invalid_backup', 'missing_log'] as $case) {
            FsmtpTest::case('managed mail outcome ' . $case, static function () use ($case) {
                global $wp_current_filter;
                $before = $wp_current_filter;
                $command = 'eval-file ' . escapeshellarg(__FILE__) . ' outcome-child ' . escapeshellarg($case);
                $result = WP_CLI::runcommand($command, [
                    'launch' => true, 'return' => 'all', 'exit_error' => false,
                ]);
                FsmtpTest::assertSame(0, $result->return_code, $result->stdout . $result->stderr);
                FsmtpTest::assertSame($before, $wp_current_filter, 'surrounding WordPress hooks');
            });
        }
    };
}

require_once dirname(__DIR__) . '/lib/harness.php';
FsmtpTest::boot();
$scenario = $args[1];
FsmtpTest::case('managed mail outcome ' . $scenario, static function () use ($scenario) {
    FsmtpTest::assertMailSimulationActive();
    // Exercise the same wp_mail and SendGrid path on master and on the
    // feature branch. The one-shot hook leaves backup simulation intact.
    $sendPrimary = static function ($log, $settings, $simulator) use (&$sendPrimary) {
        remove_filter('fluentmail_will_log_email', $sendPrimary, PHP_INT_MIN);
        $provider = new Handler();
        $provider->setSettings([
            'provider' => 'sendgrid', 'key_store' => 'db', 'api_key' => 'fixture',
            'sender_email' => 'primary@example.test', 'sender_name' => 'Fixture',
        ]);
        $provider->setPhpMailer($simulator->getPhpMailer())->send();
        return false;
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
    $actionsBefore = did_action('fluentmail_delivery_outcome');
    $allEvents = [];
    $observeAll = static function ($name, ...$args) use (&$allEvents) {
        if ($name === 'fluentmail_delivery_outcome') {
            $allEvents[] = $args[0];
        }
    };
    add_filter('pre_option_fluentmail-settings', $options, PHP_INT_MAX);
    add_filter('pre_option__fsmtp_last_notification_sent', $throttle, PHP_INT_MAX);
    add_action('all', $observeAll, 10, 3);
    fluentMailGetSettings([], false);
    $providerCalls = 0;
    FsmtpTest::interceptHttp(static function () use ($scenario, &$providerCalls, $observer) {
        ++$providerCalls;
        // A subscriber may be installed after this send has started.
        add_action('fluentmail_delivery_outcome', $observer, 10, 2);
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
        add_filter('fluentmail_will_log_email', $sendPrimary, PHP_INT_MIN, 3);
        $sent = wp_mail('recipient@example.test', FsmtpTest::uniq('outcome'), 'Fixture', 'From: primary@example.test');
        FsmtpTest::assertSame(1, $providerCalls, 'one intercepted primary request');
        $succeeded = in_array($scenario, ['sent_via_fallback', 'primary_success'], true);
        $outcome = $succeeded ? 'sent_via_fallback' : 'failed';
        $expected = $scenario === 'primary_success' ? [] : [[$outcome, $scenario === 'missing_log' ? 0 : 'positive']];
        FsmtpTest::assertSame($succeeded, $sent, 'send result');
        FsmtpTest::assertSame($expected, $events, 'observable outcomes');
        FsmtpTest::assertSame(array_column($expected, 0), $allEvents, 'wildcard outcomes');
        FsmtpTest::assertSame(count($expected), did_action('fluentmail_delivery_outcome') - $actionsBefore, 'native action count');
    } finally {
        remove_filter('fluentmail_will_log_email', $sendPrimary, PHP_INT_MIN);
        remove_filter('query', $rejectLogInsert, PHP_INT_MAX);
        $wpdb->suppress_errors($previousSuppression);
        $wpdb->query('ROLLBACK');
        remove_action('fluentmail_delivery_outcome', $observer);
        remove_action('all', $observeAll);
        remove_filter('pre_option_fluentmail-settings', $options, PHP_INT_MAX);
        remove_filter('pre_option__fsmtp_last_notification_sent', $throttle, PHP_INT_MAX);
        fluentMailGetSettings([], false);
        FsmtpTest::interceptHttp();
    }
});
FsmtpTest::finish('delivery outcome');
