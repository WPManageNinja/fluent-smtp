<?php
/**
 * Temporary MU-plugin for the authenticated FluentSMTP browser flows.
 *
 * Copy this exact file to wp-content/mu-plugins only for the browser run and
 * remove it afterwards. It closes every transport before FluentSMTP loads,
 * disables logging, and prevents the E2E flows from changing real settings.
 */

if (!defined('FLUENTMAIL_SIMULATE_EMAILS')) {
    define('FLUENTMAIL_SIMULATE_EMAILS', true);
}

if (!defined('FLUENTMAIL_LOG_OFF')) {
    define('FLUENTMAIL_LOG_OFF', true);
}

add_filter('fluentmail_will_log_email', '__return_false', PHP_INT_MAX);

add_filter('pre_http_request', function ($preempt, $args, $url) {
    return new WP_Error(
        'fsmtp_e2e_http_blocked',
        'FluentSMTP E2E safety fixture blocked outbound HTTP.'
    );
}, PHP_INT_MAX, 3);

add_filter('pre_update_option_fluentmail-settings', function ($newValue, $oldValue) {
    return $oldValue;
}, PHP_INT_MAX, 2);

// A blank fixture site still needs the configured-app branch for the dashboard
// and test-email flows. Supply a request-only, non-secret connection without
// changing the stored option; Simulator resolves before this SMTP data is used.
$fsmtpE2eVirtualSettings = function ($settings) {
    if (!empty($settings['connections'])) {
        return $settings;
    }

    $settings = is_array($settings) ? $settings : [];
    $connectionKey = 'fsmtp_e2e_virtual';
    $sender = 'fsmtp-e2e-sender@example.test';

    $settings['connections'] = [
        $connectionKey => [
            'title'             => 'FluentSMTP E2E Simulator',
            'provider_settings' => [
                'provider'         => 'smtp',
                'sender_email'     => $sender,
                'sender_name'      => 'FluentSMTP E2E',
                'host'             => 'smtp.example.test',
                'port'             => 2525,
                'auth'             => 'no',
                'encryption'       => 'none',
                'force_from_name'  => 'no',
                'force_from_email' => 'no',
            ],
        ],
    ];
    $settings['mappings'] = [$sender => $connectionKey];
    $settings['misc'] = array_merge([
        'log_emails'              => 'no',
        'log_saved_interval_days' => '14',
        'disable_fluentcrm_logs'  => 'no',
        'default_connection'      => $connectionKey,
    ], isset($settings['misc']) && is_array($settings['misc']) ? $settings['misc'] : []);
    $settings['misc']['default_connection'] = $connectionKey;

    return $settings;
};

add_filter('option_fluentmail-settings', $fsmtpE2eVirtualSettings, PHP_INT_MAX);
add_filter('default_option_fluentmail-settings', $fsmtpE2eVirtualSettings, PHP_INT_MAX);

// The E2E runner adds a per-run query nonce. Carry it onto FluentSMTP assets
// so proof-of-catch rebuilds cannot be hidden by the browser cache.
$fsmtpE2eAssetNonce = isset($_GET['fsmtp_e2e'])
    ? sanitize_key(wp_unslash($_GET['fsmtp_e2e']))
    : '';

$fsmtpE2eBustAsset = function ($src) use ($fsmtpE2eAssetNonce) {
    if ($fsmtpE2eAssetNonce && strpos($src, '/fluent-smtp/') !== false) {
        return add_query_arg('fsmtp_e2e_asset', $fsmtpE2eAssetNonce, $src);
    }

    return $src;
};

add_filter('script_loader_src', $fsmtpE2eBustAsset, PHP_INT_MAX);
add_filter('style_loader_src', $fsmtpE2eBustAsset, PHP_INT_MAX);

add_action('admin_footer', function () {
    $simulatorActive = false;

    if (function_exists('fluentMailGetProvider')) {
        $provider = fluentMailGetProvider('fsmtp-e2e-safety@example.test', true);
        $simulatorActive = $provider instanceof \FluentMail\App\Services\Mailer\Providers\Simulator\Handler;
    }

    printf(
        '<div id="fsmtp-e2e-safety" data-simulator="%1$s" data-log-fuse="%2$s" data-http-fuse="%3$s" data-settings-fuse="%4$s" hidden></div>',
        $simulatorActive ? 'active' : 'inactive',
        defined('FLUENTMAIL_LOG_OFF') && FLUENTMAIL_LOG_OFF ? 'active' : 'inactive',
        has_filter('pre_http_request') ? 'active' : 'inactive',
        has_filter('pre_update_option_fluentmail-settings') ? 'active' : 'inactive'
    );
}, PHP_INT_MAX);
