<?php

use FluentMail\App\Models\Settings;

/**
 * fluentMailSaveConnection(): code outside the admin screen saves a connection with
 * the same steps as the "Save Connection Settings" button (ConnectionSaver).
 *
 * Every case runs inside a rolled-back transaction and checks the settings option is
 * byte-for-byte what it was.
 */
return function () {
    /** Run $callback with the settings option rolled back afterwards. */
    $isolated = function (callable $callback) {
        global $wpdb;

        $fingerprint = function () use ($wpdb) {
            $value = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                'fluentmail-settings'
            ));
            return $value === null ? null : hash('sha256', $value);
        };

        $before = $fingerprint();
        $wpdb->query('START TRANSACTION');

        try {
            // Simulator on, logging off, as the other connection cases seed it.
            (new Settings())->saveGlobalSettings(array_replace_recursive(fluentMailGetSettings([], false) ?: [], [
                'misc' => ['simulate_emails' => 'yes', 'log_emails' => 'no'],
            ]));
            fluentMailGetSettings([], false);

            return $callback();
        } finally {
            $wpdb->query('ROLLBACK');
            wp_cache_delete('fluentmail-settings', 'options');
            wp_cache_delete('alloptions', 'options');
            fluentMailGetSettings([], false);
            FsmtpTest::assertSame($before, $fingerprint(), 'settings option restored');
        }
    };

    $smtp = function ($sender) {
        return [
            'provider'         => 'smtp',
            'sender_name'      => 'Suite &amp; Co',
            'sender_email'     => $sender,
            'force_from_name'  => 'no',
            'force_from_email' => 'yes',
            'return_path'      => 'yes',
            'host'             => 'smtp.api.example.test',
            'port'             => '587',
            'auth'             => 'yes',
            'username'         => 'suite-user',
            'password'         => '  suite-api-secret  ',
            'auto_tls'         => 'yes',
            'encryption'       => 'tls',
            'key_store'        => 'db',
        ];
    };

    FsmtpTest::case('fluentMailSaveConnection() adds a connection the way the admin screen does', function () use ($isolated, $smtp) {
        FsmtpTest::assertMailSimulationActive();

        $isolated(function () use ($smtp) {
            global $wpdb;
            $sender = FsmtpTest::uniq('api-save') . '@example.test';

            $key = fluentMailSaveConnection($smtp($sender));

            FsmtpTest::assertSame(md5($sender), $key, 'returns the connection key');
            $settings = fluentMailGetSettings([], false);
            $stored = $settings['connections'][$key]['provider_settings'];
            FsmtpTest::assertSame($key, $settings['mappings'][$sender], 'the sender maps to the connection');
            FsmtpTest::assertSame('smtp.api.example.test', $stored['host'], 'stored host');
            FsmtpTest::assertSame('suite-api-secret', $stored['password'], 'password trimmed, as the admin screen does');
            FsmtpTest::assertSame('Suite & Co', $stored['sender_name'], 'escaped sender name decoded, as the admin screen does');

            $raw = (string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'fluentmail-settings'));
            FsmtpTest::assert(strpos($raw, 'suite-api-secret') === false, 'the password is not stored in plain text');
        });
    });

    FsmtpTest::case('fluentMailSaveConnection() returns the field errors and stores nothing when a field is missing', function () use ($isolated, $smtp) {
        FsmtpTest::assertMailSimulationActive();

        $isolated(function () use ($smtp) {
            $sender = FsmtpTest::uniq('api-invalid') . '@example.test';
            $connection = $smtp($sender);
            $connection['host'] = '';

            $result = fluentMailSaveConnection($connection);

            FsmtpTest::assert(is_wp_error($result), 'a WP_Error comes back');
            FsmtpTest::assertSame('validation_failed', $result->get_error_code(), 'error code');
            FsmtpTest::assert(isset($result->get_error_data()['host']), 'the error names the host field');
            $settings = fluentMailGetSettings([], false);
            FsmtpTest::assert(!isset($settings['mappings'][$sender]), 'nothing was stored');
        });
    });

    FsmtpTest::case('fluentMailSaveConnection() refuses an unknown provider', function () use ($isolated, $smtp) {
        FsmtpTest::assertMailSimulationActive();

        $isolated(function () use ($smtp) {
            $connection = $smtp(FsmtpTest::uniq('api-unknown') . '@example.test');
            $connection['provider'] = 'no-such-provider';

            $result = fluentMailSaveConnection($connection);

            FsmtpTest::assert(is_wp_error($result), 'a WP_Error comes back');
            FsmtpTest::assertSame('connection_failed', $result->get_error_code(), 'error code');
        });
    });

    FsmtpTest::case('the admin route and fluentMailSaveConnection() store the same connection', function () use ($isolated, $smtp) {
        FsmtpTest::assertMailSimulationActive();

        $viaRoute = $isolated(function () use ($smtp) {
            $sender = 'api-parity@example.test';
            $result = FsmtpTest::ajax('POST', '/settings', ['connection' => $smtp($sender), 'connection_key' => '']);
            FsmtpTest::assertSame(200, $result['status'], 'route: HTTP status');
            return fluentMailGetSettings([], false)['connections'][md5($sender)]['provider_settings'];
        });
        $viaFunction = $isolated(function () use ($smtp) {
            $sender = 'api-parity@example.test';
            $key = fluentMailSaveConnection($smtp($sender));
            return fluentMailGetSettings([], false)['connections'][$key]['provider_settings'];
        });

        FsmtpTest::assertSame($viaRoute, $viaFunction, 'stored connection settings are identical');
    });
};
