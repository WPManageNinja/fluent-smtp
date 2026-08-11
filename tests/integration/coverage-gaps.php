<?php

use FluentMail\App\Services\Converter;
use FluentMail\App\Services\Html2Text;

return function () {
    FsmtpTest::case('WP Mail SMTP import preserves a complete SMTP connection mapping', function () {
        global $wpdb;

        $optionName = 'wp_mail_smtp';
        $fingerprint = function () use ($wpdb, $optionName) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",
                    $optionName
                ),
                ARRAY_A
            );
            return $row ? hash('sha256', $row['option_value'] . '|' . $row['autoload']) : null;
        };
        $before = $fingerprint();
        $fixture = [
            'mail' => [
                'from_name'        => 'Coverage Sender',
                'from_email'       => 'coverage-sender@example.test',
                'from_name_force'  => 1,
                'from_email_force' => 1,
                'return_path'      => 1,
                'mailer'           => 'smtp',
            ],
            'smtp' => [
                'host'       => 'smtp.example.test',
                'port'       => 2525,
                'auth'       => 0,
                'user'       => 'coverage-user',
                'pass'       => '',
                'auto_tls'   => 1,
                'encryption' => 'tls',
            ],
        ];

        $wpdb->query('START TRANSACTION');
        try {
            update_option($optionName, $fixture, false);
            wp_cache_delete($optionName, 'options');
            wp_cache_delete('alloptions', 'options');

            $suggestion = (new Converter())->getSuggestedConnection();
            $settings = isset($suggestion['settings']) ? $suggestion['settings'] : [];

            FsmtpTest::assertSame('smtp', isset($settings['provider']) ? $settings['provider'] : null, 'imported provider');
            FsmtpTest::assertSame('Coverage Sender', isset($settings['sender_name']) ? $settings['sender_name'] : null, 'imported sender name');
            FsmtpTest::assertSame('coverage-sender@example.test', isset($settings['sender_email']) ? $settings['sender_email'] : null, 'imported sender email');
            FsmtpTest::assertSame('yes', isset($settings['force_from_name']) ? $settings['force_from_name'] : null, 'imported force-from name');
            FsmtpTest::assertSame('yes', isset($settings['force_from_email']) ? $settings['force_from_email'] : null, 'imported force-from email');
            FsmtpTest::assertSame('yes', isset($settings['return_path']) ? $settings['return_path'] : null, 'imported return path');
            FsmtpTest::assertSame('smtp.example.test', isset($settings['host']) ? $settings['host'] : null, 'imported host');
            FsmtpTest::assertSame(2525, isset($settings['port']) ? $settings['port'] : null, 'imported port');
            FsmtpTest::assertSame('no', isset($settings['auth']) ? $settings['auth'] : null, 'imported auth mode');
            FsmtpTest::assertSame('coverage-user', isset($settings['username']) ? $settings['username'] : null, 'imported username');
            FsmtpTest::assertSame('', isset($settings['password']) ? $settings['password'] : null, 'imported empty password');
            FsmtpTest::assertSame('yes', isset($settings['auto_tls']) ? $settings['auto_tls'] : null, 'imported automatic TLS');
            FsmtpTest::assertSame('tls', isset($settings['encryption']) ? $settings['encryption'] : null, 'imported encryption');
            FsmtpTest::assertSame('db', isset($settings['key_store']) ? $settings['key_store'] : null, 'imported key store');
        } finally {
            $wpdb->query('ROLLBACK');
            wp_cache_delete($optionName, 'options');
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
            FsmtpTest::assertSame($before, $fingerprint(), 'WP Mail SMTP option restoration');
        }
    });

    FsmtpTest::case('HTML-to-text conversion preserves readable structure and removes active markup', function () {
        $html = '<h1>Delivery <strong>Report</strong></h1>'
            . '<p>Hello &amp; welcome.</p>'
            . '<ul><li>First</li><li>Second</li></ul>'
            . '<p><a href="https://example.test/docs">Docs</a></p>'
            . '<script>suite-script-content</script>';
        $text = (new Html2Text($html))->getText();

        FsmtpTest::assert(strpos($text, 'DELIVERY REPORT') !== false, 'plain text heading was lost');
        FsmtpTest::assert(strpos($text, 'Hello & welcome.') !== false, 'plain text entity decoding failed');
        FsmtpTest::assert(strpos($text, "\t* First") !== false, 'plain text first list item was lost');
        FsmtpTest::assert(strpos($text, "\t* Second") !== false, 'plain text second list item was lost');
        FsmtpTest::assert(strpos($text, 'Docs [https://example.test/docs]') !== false, 'plain text link target was lost');
        FsmtpTest::assert(strpos($text, 'suite-script-content') === false, 'script content leaked into plain text');
        FsmtpTest::assert(strpos($text, '<') === false, 'HTML markup remained in plain text');
    });
};
