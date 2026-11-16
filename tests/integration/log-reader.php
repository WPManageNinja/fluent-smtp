<?php

use FluentMail\App\Hooks\Handlers\AdminMenuHandler;

return function () {
    global $wpdb;
    $admin = get_current_user_id();
    $username = FsmtpTest::uniq('log-reader');
    $subscriberId = wp_insert_user([
        'user_login' => $username, 'user_email' => $username . '@example.test',
        'user_pass' => wp_generate_password(24, true, true), 'role' => 'subscriber'
    ]);
    if (is_wp_error($subscriberId)) { throw new RuntimeException('Could not create reader fixture.'); }
    $subscriber = get_user_by('id', $subscriberId);
    $reader = true;
    $grant = function ($caps, $requested, $args, $user) use (&$reader, $subscriber) {
        if ($user->ID === $subscriber->ID) {
            $caps['fluentmail_view_logs'] = $reader;
        }
        return $caps;
    };
    add_filter('user_has_cap', $grant, 10, 4);
    $table = FsmtpFactory::emailLogTable();
    $id = FsmtpFactory::insertLog($table, [
        'subject' => 'Reader legacy & subject',
        'body' => 'Reader fixture body',
        'attachments' => maybe_serialize([['/private/local/reader.pdf', 'reader.pdf', '', '', '', false]]),
    ]);
    $redirect = FsmtpFactory::productionLogTableRedirect($table);
    add_filter('query', $redirect, PHP_INT_MAX);
    try {
        wp_set_current_user($subscriber->ID);
        FsmtpTest::case('log reader can search and view without raw attachment paths', function () use ($id) {
            $list = FsmtpTest::ajax('GET', 'logs', ['page' => 1, 'per_page' => 10, 'search' => 'Reader legacy']);
            FsmtpTest::assertSame(200, $list['status'], 'reader list status');
            FsmtpTest::assert(strpos($list['raw'], 'Reader legacy') !== false, 'reader list fixture');
            FsmtpTest::assert(strpos(wp_json_encode($list['data'], JSON_UNESCAPED_SLASHES), '/private/local') === false, 'reader list filesystem path omitted');
            $detail = FsmtpTest::ajax('GET', 'logs/show', ['id' => $id - 1, 'dir' => 'next']);
            FsmtpTest::assertSame(200, $detail['status'], 'reader viewer status');
            FsmtpTest::assert(strpos($detail['raw'], 'Reader fixture body') !== false, 'reader body available');
            FsmtpTest::assert(strpos($detail['raw'], 'reader.pdf') !== false, 'reader attachment name available');
            FsmtpTest::assert(strpos(wp_json_encode($detail['data'], JSON_UNESCAPED_SLASHES), '/private/local') === false, 'reader viewer filesystem path omitted');
        });
        $routes = require dirname(__DIR__) . '/smoke/routes.manifest.php';
        foreach ($routes as $entry) {
            if (in_array($entry['route'], ['/logs', 'logs', '/logs/show', 'logs/show'], true)) { continue; }
            FsmtpTest::case('log reader denied ' . $entry['method'] . ' ' . $entry['route'], function () use ($entry) {
                $result = FsmtpTest::ajax($entry['method'], $entry['route'], $entry['cases'][0]['params']);
                FsmtpTest::assertSame(403, $result['status'], 'management endpoint requires management capability');
            });
        }
        FsmtpTest::case('log reader bootstrap and top-level menu contain no manager configuration', function () {
            global $menu, $submenu, $wp_scripts, $wp_styles;
            $saved = [$menu, $submenu, $wp_scripts, $wp_styles];
            $extensionCalls = 0;
            $extension = function () use (&$extensionCalls) { $extensionCalls++; };
            add_action('fluent_mail_loading_app', $extension);
            try {
                $menu = []; $submenu = [];
                $wp_scripts = null; $wp_styles = null;
                $handler = new AdminMenuHandler(fluentMail());
                $handler->addMenu();
                $matches = array_values(array_filter($menu, function ($item) { return $item[2] === 'fluent-mail'; }));
                FsmtpTest::assertSame(1, count($matches), 'reader top-level menu');
                $handler->enqueueAssets();
                FsmtpTest::assertSame(0, $extensionCalls, 'reader does not invoke manager app extensions');
                $data = wp_scripts()->get_data('fluent_mail_admin_app_boot', 'data');
                FsmtpTest::assert(strpos($data, '"manage":false') !== false, 'reader bootstrap permission');
                foreach (['"connections"', '"mappings"', '"api_key"', '"password"', '"access_token"'] as $field) {
                    FsmtpTest::assert(strpos($data, $field) === false, 'reader bootstrap omits ' . $field);
                }
            } finally {
                remove_action('fluent_mail_loading_app', $extension);
                list($menu, $submenu, $wp_scripts, $wp_styles) = $saved;
            }
        });
        FsmtpTest::case('reader access still requires a valid nonce', function () {
            $hook = FsmtpTest::ajaxAction('GET', 'logs');
            $invalidNonce = function () { fluentMail('request')->set('nonce', 'invalid-reader-nonce'); };
            add_action($hook, $invalidNonce, 0);
            try {
                $result = FsmtpTest::ajax('GET', 'logs', ['page' => 1, 'per_page' => 10]);
                FsmtpTest::assertSame(403, $result['status'], 'reader invalid nonce');
            } finally {
                remove_action($hook, $invalidNonce, 0);
            }
        });
        $reader = false;
        foreach ([$subscriber->ID, 0] as $userId) {
            wp_set_current_user($userId);
            FsmtpTest::case('ungranted user ' . $userId . ' cannot read logs', function () use ($id) {
                FsmtpTest::assertSame(403, FsmtpTest::ajax('GET', 'logs', ['page' => 1, 'per_page' => 10])['status'], 'ungranted list');
                FsmtpTest::assertSame(403, FsmtpTest::ajax('GET', 'logs/show', ['id' => $id - 1, 'dir' => 'next'])['status'], 'ungranted detail');
            });
        }
        wp_set_current_user($admin);
        FsmtpTest::case('existing manager retains full log payload', function () {
            $result = FsmtpTest::ajax('GET', 'logs', ['page' => 1, 'per_page' => 10]);
            FsmtpTest::assertSame(200, $result['status'], 'manager list');
            FsmtpTest::assert(strpos(wp_json_encode($result['data'], JSON_UNESCAPED_SLASHES), '/private/local') !== false, 'manager attachment contract unchanged');
        });
        FsmtpTest::case('manager page still invokes existing app extensions', function () {
            $calls = 0;
            $extension = function () use (&$calls) { $calls++; };
            add_action('fluent_mail_loading_app', $extension);
            try {
                (new AdminMenuHandler(fluentMail()))->enqueueAssets();
                FsmtpTest::assertSame(1, $calls, 'manager app extension hook');
            } finally {
                remove_action('fluent_mail_loading_app', $extension);
            }
        });
    } finally {
        wp_set_current_user($admin);
        remove_filter('user_has_cap', $grant, 10);
        remove_filter('query', $redirect, PHP_INT_MAX);
        if (!function_exists('wp_delete_user')) { require_once ABSPATH . 'wp-admin/includes/user.php'; }
        wp_delete_user($subscriber->ID);
    }
};
