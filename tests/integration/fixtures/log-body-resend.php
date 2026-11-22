<?php

use FluentMail\App\Models\Logger;

require_once dirname(__DIR__, 2) . '/lib/harness.php';
require_once dirname(__DIR__, 2) . '/lib/factory.php';

FsmtpTest::boot();
FsmtpTest::assertMailSimulationActive();
$table = FsmtpFactory::emailLogTable();
$redirect = FsmtpFactory::productionLogTableRedirect($table);
$sent = [];
$expireDuringDelivery = 0;
$intercept = static function ($result, $mail) use (&$sent, &$expireDuringDelivery, $table) {
    $sent[] = $mail['subject'];
    if ($expireDuringDelivery) {
        global $wpdb;
        $wpdb->delete($table, ['id' => $expireDuringDelivery]);
        $expireDuringDelivery = 0;
    }
    return true;
};
add_filter('pre_wp_mail', $intercept, PHP_INT_MAX, 2);
add_filter('query', $redirect, PHP_INT_MAX);
try {
    $omitted = FsmtpFactory::insertLog($table, ['body' => '', 'extra' => serialize(['body_omitted' => true])]);
    $legacy = FsmtpFactory::insertLog($table, ['body' => '', 'subject' => 'Retained empty body']);
    $single = FsmtpTest::ajax('POST', '/logs/retry', ['id' => $omitted, 'type' => 'retry']);
    $only = FsmtpTest::ajax('POST', '/logs/retry-bulk', ['log_ids' => [$omitted]]);
    $mixed = FsmtpTest::ajax('POST', '/logs/retry-bulk', ['log_ids' => [$omitted, $legacy]]);
    if ($single['status'] !== 409 || $only['data']['data']['body_omitted'] !== 1 ||
        $mixed['data']['data']['body_omitted'] !== 1 || $sent !== ['Retained empty body'] ||
        (int) (new Logger())->find($omitted)['retries'] !== 0) {
        throw new RuntimeException('Single or bulk resend did not respect the omission marker.');
    }

    // A retention job can remove a row between delivery and updating its retry count.
    $expireDuringDelivery = FsmtpFactory::insertLog($table, ['subject' => 'Expired after delivery']);
    $expired = FsmtpTest::ajax('POST', '/logs/retry-bulk', ['log_ids' => [$omitted, $expireDuringDelivery]]);
    $summary = $expired['data']['data'];
    if (end($sent) !== 'Expired after delivery' || strpos($summary['message'], 'Emails resent: 1.') === false ||
        empty($summary['log_update_failed'])) {
        throw new RuntimeException('Bulk resend must count successful delivery even when its log update fails.');
    }
} finally {
    remove_filter('pre_wp_mail', $intercept, PHP_INT_MAX);
    remove_filter('query', $redirect, PHP_INT_MAX);
    FsmtpFactory::cleanup();
}
