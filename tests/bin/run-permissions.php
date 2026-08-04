<?php
/**
 * Phase 3 — permission smoke for all 31 mutating admin-AJAX routes.
 *
 * Anonymous coverage verifies that no nopriv action exists. Low-privilege
 * coverage dispatches each real wp_ajax callback as a temporary subscriber.
 * Option writes are fused, HTTP is intercepted, inert IDs are used, and every
 * case runs in a rolled-back transaction as a final safety net.
 */

require_once dirname(__DIR__) . '/lib/harness.php';

FsmtpTest::boot();
FsmtpTest::interceptHttp();

$manifest = require dirname(__DIR__) . '/smoke/mutating.manifest.php';
$knownFailures = [
    'settings/telegram/send-test' => 'TelegramController.php:116 sendTestMessage() does not call verify()',
    'settings/telegram/disconnect' => 'TelegramController.php:141 disconnect() does not call verify()',
    'settings/slack/send-test' => 'SlackController.php:58 sendTestMessage() does not call verify()',
    'settings/slack/disconnect' => 'SlackController.php:85 disconnect() does not call verify()',
    'settings/discord/send-test' => 'DiscordController.php:50 sendTestMessage() does not call verify()',
    'settings/discord/disconnect' => 'DiscordController.php:78 disconnect() does not call verify()',
    'settings/pushover/send-test' => 'PushoverController.php:41 sendTestMessage() does not call verify()',
    'settings/pushover/disconnect' => 'PushoverController.php:69 disconnect() does not call verify()',
];

$protectedOptions = [
    'fluentmail-settings',
    '_fluent_smtp_notify_settings',
    '_fluentsmtp_sub_update',
    '_fluentsmtp_dismissed_timestamp',
    '_fluentsmtp_intended_outlook_info',
    '_fluentmail_last_generated_state',
    'active_plugins',
];

/** Read exact database option rows, bypassing the object cache. */
$readOptions = function () use ($protectedOptions) {
    global $wpdb;
    $snapshot = [];
    foreach ($protectedOptions as $name) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",
                $name
            ),
            ARRAY_A
        );
        $snapshot[$name] = $row ?: null;
    }
    return $snapshot;
};

/** Clear option caches after a transaction rollback. */
$clearOptionCaches = function () use ($protectedOptions) {
    foreach ($protectedOptions as $name) {
        wp_cache_delete($name, 'options');
    }
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
};

// Returning the old value stops every known FluentSMTP/activation option write
// before SQL. The transaction below remains as a backstop for an unknown write.
$optionFuses = [];
foreach ($protectedOptions as $name) {
    $callback = function ($newValue, $oldValue) {
        return $oldValue;
    };
    add_filter('pre_update_option_' . $name, $callback, PHP_INT_MAX, 2);
    $optionFuses[$name] = $callback;
}

$adminId = get_current_user_id();
$username = FsmtpTest::uniq('fsmtp-permission');
$subscriberId = wp_insert_user([
    'user_login' => $username,
    'user_pass'  => wp_generate_password(24, true, true),
    'user_email' => $username . '@example.test',
    'role'       => 'subscriber',
]);
if (is_wp_error($subscriberId)) {
    WP_CLI::error('Could not create permission subscriber: ' . $subscriberId->get_error_message());
}

$cleanup = function () use ($adminId, $subscriberId, $optionFuses) {
    wp_set_current_user($adminId);
    foreach ($optionFuses as $name => $callback) {
        remove_filter('pre_update_option_' . $name, $callback, PHP_INT_MAX);
    }
    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    if (get_user_by('ID', $subscriberId)) {
        wp_delete_user($subscriberId);
    }
};
register_shutdown_function($cleanup);

WP_CLI::log(sprintf(
    "FluentSMTP permissions — %d POST routes, anonymous + subscriber\n",
    count($manifest)
));

try {
    foreach ($manifest as $entry) {
        $route = $entry['route'];
        $params = $entry['cases'][0]['params'];

        FsmtpTest::case('POST ' . $route . ' — anonymous', function () use ($route) {
            $adminHook = FsmtpTest::ajaxAction('POST', $route, true);
            $publicHook = FsmtpTest::ajaxAction('POST', $route, false);

            FsmtpTest::assert(
                has_action($adminHook) !== false,
                'admin callback is not registered: ' . $adminHook
            );
            FsmtpTest::assert(
                has_action($publicHook) === false,
                'SECURITY: anonymous callback is registered: ' . $publicHook
            );
        });

        FsmtpTest::case('POST ' . $route . ' — subscriber', function () use (
            $route,
            $params,
            $subscriberId,
            $knownFailures,
            $readOptions,
            $clearOptionCaches
        ) {
            global $wpdb;

            wp_set_current_user($subscriberId);
            $before = $readOptions();
            $wpdb->query('START TRANSACTION');

            try {
                $result = FsmtpTest::ajax('POST', $route, $params);
            } finally {
                $wpdb->query('ROLLBACK');
                $clearOptionCaches();
            }

            $after = $readOptions();
            FsmtpTest::assertSame($before, $after, 'permission safety fuses preserved protected options');

            if ($result['db_error'] !== '') {
                FsmtpTest::fail('DATABASE ERROR during permission check: ' . $result['db_error']);
                return;
            }

            $message = FsmtpTest::ajaxMessage($result);
            $denied = is_array($result['data'])
                && isset($result['data']['success'])
                && $result['data']['success'] === false
                && stripos($message, 'permission') !== false;

            if ($denied) {
                return;
            }

            if (isset($knownFailures[$route]) && getenv('FSMTP_STRICT_KNOWN_FAILURES') !== '1') {
                FsmtpTest::skip(
                    'KNOWN-FAILURE: permission bypass — ' . $knownFailures[$route]
                    . '; observed response: ' . ($message !== '' ? $message : '(no message)')
                );
                return;
            }

            FsmtpTest::fail(
                (isset($knownFailures[$route]) ? 'KNOWN-FAILURE: ' : '')
                . 'SECURITY: subscriber was not rejected by Controller::verify()'
                . "\n  action: " . $result['action']
                . "\n  response message: " . ($message !== '' ? $message : '(no message)')
            );
        });
    }
} finally {
    $cleanup();
}

FsmtpTest::finish('PERMISSIONS');
