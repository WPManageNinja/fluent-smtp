<?php
/** Safety bootstrap loaded by every WP-CLI subprocess integration case. */

if (!defined('FLUENTMAIL_SIMULATE_EMAILS')) {
    define('FLUENTMAIL_SIMULATE_EMAILS', true);
}
if (!defined('FLUENTMAIL_LOG_OFF')) {
    define('FLUENTMAIL_LOG_OFF', true);
}

if (!class_exists('WP_CLI')) {
    throw new RuntimeException('The FluentSMTP CLI safety bootstrap requires WP-CLI.');
}

$encodedSettings = getenv('FSMTP_SUITE_SETTINGS_B64');
$decodedSettings = $encodedSettings ? base64_decode($encodedSettings, true) : false;
$suiteSettings = $decodedSettings === false ? null : json_decode($decodedSettings, true);

if (!is_array($suiteSettings)) {
    WP_CLI::error('FluentSMTP CLI suite settings are missing or invalid.');
}

WP_CLI::add_hook('after_wp_load', function () use ($suiteSettings) {
    add_filter('pre_option_fluentmail-settings', function () use ($suiteSettings) {
        return $suiteSettings;
    }, PHP_INT_MAX);

    // Health must be isolated from both an old report and persistent writes.
    add_filter('pre_option__fluentsmtp_connection_health', function () {
        return [];
    }, PHP_INT_MAX);
    add_filter('pre_update_option__fluentsmtp_connection_health', function ($newValue, $oldValue) {
        return $oldValue;
    }, PHP_INT_MAX, 2);

    add_filter('pre_schedule_event', function () {
        return false;
    }, PHP_INT_MAX, 3);
    add_filter('fluentmail_will_log_email', '__return_false', PHP_INT_MAX);
    add_filter('query', function ($query) {
        global $wpdb;

        $table = $wpdb->prefix . FLUENT_MAIL_DB_PREFIX . 'email_logs';
        $deletePattern = '/^\s*DELETE\s+FROM\s+`?'
            . preg_quote($table, '/') . '`?(?:\s|$)/i';
        if (!preg_match($deletePattern, $query)) {
            return $query;
        }

        // Child CLI coverage is allowed to reach the real pruning command, but
        // no test subprocess is allowed to change a production log row. Insert
        // the impossible predicate before LIMIT for both healthy and mutated SQL.
        $limitOffset = stripos($query, ' LIMIT ');
        $head = $limitOffset === false ? $query : substr($query, 0, $limitOffset);
        $tail = $limitOffset === false ? '' : substr($query, $limitOffset);
        $predicate = stripos($head, ' WHERE ') === false ? ' WHERE 1 = 0' : ' AND 1 = 0';

        WP_CLI::log('FluentSMTP CLI safety: production log DELETE fused.');
        return $head . $predicate . $tail;
    }, PHP_INT_MAX);
    add_filter('pre_http_request', function ($preempt, $args, $url) {
        return new WP_Error(
            'fsmtp_cli_test_http_blocked',
            'Outbound HTTP blocked by the FluentSMTP CLI suite: ' . strtok((string)$url, '?')
        );
    }, PHP_INT_MAX, 3);

    if (!function_exists('fluentMailGetProvider')) {
        WP_CLI::error('FluentSMTP is not active in the CLI child process.');
    }

    $provider = fluentMailGetProvider('fsmtp-cli-safety@example.test', true);
    if (!$provider instanceof \FluentMail\App\Services\Mailer\Providers\Simulator\Handler) {
        WP_CLI::error('FluentSMTP CLI safety check did not resolve the Simulator provider.');
    }

    WP_CLI::log('FluentSMTP CLI safety: Simulator active.');
});
