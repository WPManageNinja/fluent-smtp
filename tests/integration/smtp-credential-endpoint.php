<?php

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\SecretMasker;

/**
 * A saved SMTP credential stays with the endpoint it was saved for.
 *
 * The connection form sends a stored password back as the mask. A save that
 * changes the host, port or username has to carry a typed password; the stored
 * one is not reused. wp-config credentials are bound the same way when the
 * optional FLUENTMAIL_SMTP_HOST / FLUENTMAIL_SMTP_PORT constants are defined;
 * those cases run in a child process because constants cannot be undefined.
 *
 * Saves go through the real admin-AJAX route inside a rolled-back transaction.
 */
return function () {
    $savedPassword = 'suite-saved-secret';

    /** Run $callback against a seeded SMTP connection, then roll every write back. */
    $withSmtpConnection = function (callable $callback) use ($savedPassword) {
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
            $sender = FsmtpTest::uniq('smtp-endpoint') . '@example.test';
            $key = md5($sender);
            $connection = [
                'provider'         => 'smtp',
                'sender_name'      => 'Suite',
                'sender_email'     => $sender,
                'force_from_name'  => 'no',
                'force_from_email' => 'yes',
                'return_path'      => 'yes',
                'host'             => 'smtp.saved.example.test',
                'port'             => '587',
                'auth'             => 'yes',
                'username'         => 'suite-user',
                'password'         => $savedPassword,
                'auto_tls'         => 'yes',
                'encryption'       => 'tls',
                'key_store'        => 'db',
            ];
            (new Settings())->saveGlobalSettings([
                'connections' => [$key => ['title' => 'SMTP', 'provider_settings' => $connection]],
                'mappings'    => [$sender => $key],
                'misc'        => ['default_connection' => $key, 'simulate_emails' => 'yes', 'log_emails' => 'no'],
            ]);

            return $callback($key, $sender, $connection);
        } finally {
            $wpdb->query('ROLLBACK');
            wp_cache_delete('fluentmail-settings', 'options');
            wp_cache_delete('alloptions', 'options');
            fluentMailGetSettings([], false);
            FsmtpTest::assertSame($before, $fingerprint(), 'settings option restored');
        }
    };

    /** Save $connection through the route; return [status, stored connection settings]. */
    $save = function ($key, $sender, array $connection) {
        $result = FsmtpTest::ajax('POST', '/settings', ['connection' => $connection, 'connection_key' => $key]);
        $settings = fluentMailGetSettings([], false);
        $storedKey = isset($settings['mappings'][$sender]) ? $settings['mappings'][$sender] : $key;
        $stored = isset($settings['connections'][$storedKey]['provider_settings'])
            ? $settings['connections'][$storedKey]['provider_settings']
            : [];

        return [$result, $stored];
    };

    FsmtpTest::case('a masked SMTP password is refused when the host, port or username changes', function () use ($withSmtpConnection, $save, $savedPassword) {
        FsmtpTest::assertMailSimulationActive();

        foreach (['host' => 'smtp.changed.example.test', 'port' => '2525', 'username' => 'other-user'] as $field => $value) {
            $withSmtpConnection(function ($key, $sender, $connection) use ($save, $field, $value, $savedPassword) {
                $submitted = array_merge($connection, [$field => $value, 'password' => SecretMasker::MASK]);
                list($result, $stored) = $save($key, $sender, $submitted);

                FsmtpTest::assertSame(422, $result['status'], $field . ' change with masked password: HTTP status');
                FsmtpTest::assert(
                    isset($result['data']['data']['password']) || isset($result['data']['password']),
                    $field . ' change with masked password: error is on the password field'
                );
                FsmtpTest::assertSame($connection[$field], $stored[$field], $field . ' change with masked password: stored ' . $field . ' unchanged');
                FsmtpTest::assert($stored['password'] === $savedPassword, $field . ' change with masked password: stored password unchanged');
            });
        }
    });

    FsmtpTest::case('an SMTP endpoint change saves when the password is typed again', function () use ($withSmtpConnection, $save) {
        FsmtpTest::assertMailSimulationActive();

        $withSmtpConnection(function ($key, $sender, $connection) use ($save) {
            $submitted = array_merge($connection, ['host' => 'smtp.changed.example.test', 'password' => 'suite-new-secret']);
            list($result, $stored) = $save($key, $sender, $submitted);

            FsmtpTest::assertSame(200, $result['status'], 'typed password: HTTP status');
            FsmtpTest::assertSame('smtp.changed.example.test', $stored['host'], 'typed password: stored host');
            FsmtpTest::assert($stored['password'] === 'suite-new-secret', 'typed password: stored password is the typed one');
        });
    });

    FsmtpTest::case('a masked SMTP password is kept when the endpoint is unchanged', function () use ($withSmtpConnection, $save, $savedPassword) {
        FsmtpTest::assertMailSimulationActive();

        $edits = [
            'sender name'           => ['sender_name' => 'Renamed'],
            'encryption'            => ['encryption' => 'ssl'],
            'host case and spacing' => ['host' => ' SMTP.Saved.Example.Test '],
        ];
        foreach ($edits as $label => $edit) {
            $withSmtpConnection(function ($key, $sender, $connection) use ($save, $label, $edit, $savedPassword) {
                $submitted = array_merge($connection, $edit, ['password' => SecretMasker::MASK]);
                list($result, $stored) = $save($key, $sender, $submitted);

                FsmtpTest::assertSame(200, $result['status'], $label . ': HTTP status');
                FsmtpTest::assert($stored['password'] === $savedPassword, $label . ': stored password kept');
            });
        }
    });

    FsmtpTest::case('masked secrets of providers without an editable endpoint still resolve', function () {
        $stored = ['provider' => 'sendgrid', 'api_key' => 'suite-sendgrid-key'];
        $resolved = SecretMasker::resolve(['provider' => 'sendgrid', 'api_key' => SecretMasker::MASK, 'sender_name' => 'Other'], $stored);

        FsmtpTest::assert($resolved['api_key'] === 'suite-sendgrid-key', 'SendGrid masked key restored');
    });

    $child = function ($mode) {
        $result = FsmtpTest::wpCli(
            ['eval-file', __DIR__ . '/fixtures/smtp-wp-config-endpoint.php'],
            [
                'FSMTP_CHILD_MODE'          => $mode,
                'FSMTP_SUITE_SETTINGS_B64'  => base64_encode(wp_json_encode(['misc' => ['simulate_emails' => 'yes']])),
            ]
        );
        FsmtpTest::assertSame(0, $result['code'], $mode . ' child exit code');

        $observed = null;
        foreach (preg_split('/\R/', $result['stdout']) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded) && array_key_exists('same_host_uses_constants', $decoded)) {
                $observed = $decoded;
            }
        }
        FsmtpTest::assert(is_array($observed), $mode . ' child printed its observations');

        return (array) $observed;
    };

    FsmtpTest::case('wp-config SMTP credentials without a host constant keep working for the saved host', function () use ($child) {
        $observed = $child('unbound');

        FsmtpTest::assertSame(true, $observed['same_host_uses_constants'], 'saved host uses the constants');
        FsmtpTest::assertSame(true, $observed['other_host_uses_constants'], 'no host constant: any saved host still uses the constants');
        FsmtpTest::assertSame([], $observed['other_host_save_errors'], 'no host constant: save is not restricted');
    });

    FsmtpTest::case('wp-config SMTP credentials are bound to FLUENTMAIL_SMTP_HOST and FLUENTMAIL_SMTP_PORT', function () use ($child) {
        $observed = $child('bound');

        FsmtpTest::assertSame(true, $observed['same_host_uses_constants'], 'bound host uses the constants');
        FsmtpTest::assertSame(true, $observed['same_host_case_uses'], 'bound host compared without case or spacing');
        FsmtpTest::assertSame(false, $observed['other_host_uses_constants'], 'other host: constants withheld');
        FsmtpTest::assertSame(false, $observed['other_port_uses_constants'], 'other port: constants withheld');
        FsmtpTest::assertSame([], $observed['same_host_save_errors'], 'bound host saves');
        FsmtpTest::assertSame(['host'], $observed['other_host_save_errors'], 'other host: save rejected on host');
        FsmtpTest::assertSame(['port'], $observed['other_port_save_errors'], 'other port: save rejected on port');
    });
};
