<?php

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\NotificationHelper;
use FluentMail\App\Services\SecretMasker;
use FluentMail\Includes\Support\Arr;

return function () {
    /**
     * Run option mutations in a real transaction, then restore both SQL and
     * WordPress/FluentSMTP caches. Fingerprints keep real credentials out of
     * failure output while proving the original option rows were restored.
     */
    $withOptionTransaction = function (callable $callback) {
        global $wpdb;

        $optionNames = [
            'fluentmail-settings',
            '_fluent_smtp_notify_settings',
        ];
        $fingerprints = function () use ($wpdb, $optionNames) {
            $result = [];
            foreach ($optionNames as $name) {
                $row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",
                        $name
                    ),
                    ARRAY_A
                );
                $result[$name] = $row
                    ? hash('sha256', $row['option_value'] . '|' . $row['autoload'])
                    : null;
            }
            return $result;
        };

        $beforeOptions = $fingerprints();
        $beforeTables = FsmtpTest::protectedTableCounts();
        $wpdb->query('START TRANSACTION');

        try {
            return $callback();
        } finally {
            $wpdb->query('ROLLBACK');
            foreach ($optionNames as $name) {
                wp_cache_delete($name, 'options');
            }
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
            fluentMailGetSettings([], false);

            FsmtpTest::assertSame($beforeOptions, $fingerprints(), 'round-trip option restoration');
            FsmtpTest::assertSame($beforeTables, FsmtpTest::protectedTableCounts(), 'round-trip protected tables');
        }
    };

    $readSettings = function () {
        $result = FsmtpTest::ajax('GET', '/settings');
        FsmtpTest::assertAjaxHealthy($result, 'read FluentSMTP settings');
        return Arr::get($result, 'data.data.settings', []);
    };

    /**
     * One connection's settings as the mailers read them. The GET above hands
     * connections to the browser through SecretMasker, which withholds the OAuth
     * tokens entirely, so anything about a token has to be asserted here.
     */
    $storedProviderSettings = function ($connectionKey) {
        return Arr::get(fluentMailGetSettings([], false), 'connections.' . $connectionKey . '.provider_settings', []);
    };

    $readNotificationSettings = function () {
        $result = FsmtpTest::ajax('GET', 'settings/notification-settings');
        FsmtpTest::assertAjaxHealthy($result, 'read notification settings');
        return Arr::get($result, 'data.data.settings', []);
    };

    /** Assert one changed path and strict identity of the complete remainder. */
    $assertOnlyFieldChanged = function ($before, $after, $path, $expected, $label) {
        FsmtpTest::assertSame($expected, Arr::get($after, $path), $label . ' changed field');

        $beforeRemainder = $before;
        $afterRemainder = $after;
        Arr::forget($beforeRemainder, $path);
        Arr::forget($afterRemainder, $path);
        FsmtpTest::assertSame(
            $beforeRemainder,
            $afterRemainder,
            $label . ' every other field unchanged'
        );
    };

    /**
     * Seeded straight into storage, so it has to look like what a real save
     * leaves there: Request::clean() turns an empty SPA field into null before
     * any controller sees it, which is why the fallback is null and not ''.
     */
    $baseMisc = function ($defaultConnection = '') {
        return [
            'log_emails'              => 'yes',
            'log_saved_interval_days' => '14',
            'disable_fluentcrm_logs'  => 'no',
            'default_connection'      => $defaultConnection,
            'fallback_connection'     => null,
            'simulate_emails'         => 'yes',
            'send_as_text'            => 'no',
        ];
    };

    FsmtpTest::case('global settings round-trip preserves OAuth tokens during an unrelated misc save', function () use (
        $withOptionTransaction,
        $readSettings,
        $storedProviderSettings,
        $assertOnlyFieldChanged,
        $baseMisc
    ) {
        $withOptionTransaction(function () use ($readSettings, $storedProviderSettings, $assertOnlyFieldChanged, $baseMisc) {
            $sender = 'outlook-roundtrip-' . FsmtpTest::uniq() . '@example.test';
            $connectionKey = md5($sender);
            $expireStamp = time() + 7200;
            $initial = [
                'connections' => [
                    $connectionKey => [
                        'title' => 'Suite Outlook',
                        'provider_settings' => [
                            'provider'           => 'outlook',
                            'sender_name'        => 'Suite OAuth Sender',
                            'sender_email'       => $sender,
                            'force_from_name'    => 'no',
                            'force_from_email'   => 'yes',
                            'key_store'          => 'db',
                            'client_id'          => 'suite-client-id',
                            'client_secret'      => 'suite-client-secret',
                            'access_token'       => 'suite-access-token',
                            'refresh_token'      => 'suite-refresh-token',
                            'expire_stamp'       => $expireStamp,
                            'disable_encryption' => 'yes',
                        ],
                    ],
                ],
                'mappings' => [$sender => $connectionKey],
                'misc'     => $baseMisc($connectionKey),
                'suite_marker' => ['source' => 'global-roundtrip', 'version' => 1],
            ];

            FsmtpTest::assert(fluentMailSetSettings($initial), 'create global settings');
            $before = $readSettings();

            $browserView = Arr::get($before, 'connections.' . $connectionKey . '.provider_settings', []);
            FsmtpTest::assert(!array_key_exists('access_token', $browserView), 'GET handed the access token to the browser');
            FsmtpTest::assert(!array_key_exists('refresh_token', $browserView), 'GET handed the refresh token to the browser');
            FsmtpTest::assertSame('yes', Arr::get($browserView, 'has_access_token'), 'authenticated flag in place of the token');

            FsmtpTest::assertSame(
                'suite-access-token',
                Arr::get($storedProviderSettings($connectionKey), 'access_token'),
                'created OAuth access token'
            );
            FsmtpTest::assertSame(
                'suite-refresh-token',
                Arr::get($storedProviderSettings($connectionKey), 'refresh_token'),
                'created OAuth refresh token'
            );
            FsmtpTest::assertSame(
                $expireStamp,
                Arr::get($before, 'connections.' . $connectionKey . '.provider_settings.expire_stamp'),
                'created OAuth expiry'
            );

            $misc = $before['misc'];
            $misc['log_saved_interval_days'] = '30';
            $update = FsmtpTest::ajax('POST', '/misc-settings', ['settings' => $misc]);
            FsmtpTest::assertAjaxHealthy($update, 'update one global misc field');
            $after = $readSettings();

            $assertOnlyFieldChanged(
                $before,
                $after,
                'misc.log_saved_interval_days',
                '30',
                'unrelated global save'
            );
            FsmtpTest::assertSame(
                'suite-access-token',
                Arr::get($storedProviderSettings($connectionKey), 'access_token'),
                'OAuth access token after unrelated save'
            );
            FsmtpTest::assertSame(
                'suite-refresh-token',
                Arr::get($storedProviderSettings($connectionKey), 'refresh_token'),
                'OAuth refresh token after unrelated save'
            );
            FsmtpTest::assertSame(
                $expireStamp,
                Arr::get($after, 'connections.' . $connectionKey . '.provider_settings.expire_stamp'),
                'OAuth expiry after unrelated save'
            );

            if (isset($after['connections'][$connectionKey])) {
                $delete = FsmtpTest::ajax('POST', '/settings/delete', ['key' => $connectionKey]);
                FsmtpTest::assertAjaxHealthy($delete, 'delete global round-trip connection');
                $deleted = $readSettings();
                FsmtpTest::assert(!isset($deleted['connections'][$connectionKey]), 'deleted connection still exists');
                FsmtpTest::assert(!isset($deleted['mappings'][$sender]), 'deleted connection mapping still exists');
            }
        });
    });

    FsmtpTest::case('updateConnection changes one provider field and preserves the complete settings graph', function () use (
        $withOptionTransaction,
        $assertOnlyFieldChanged,
        $baseMisc
    ) {
        $withOptionTransaction(function () use ($assertOnlyFieldChanged, $baseMisc) {
            $sender = 'gmail-update-' . FsmtpTest::uniq() . '@example.test';
            $connectionKey = md5($sender);
            $initial = [
                'connections' => [
                    $connectionKey => [
                        'title' => 'Suite Gmail',
                        'provider_settings' => [
                            'provider'           => 'gmail',
                            'sender_name'        => 'Before Name',
                            'sender_email'       => $sender,
                            'force_from_name'    => 'no',
                            'force_from_email'   => 'yes',
                            'key_store'          => 'db',
                            'client_id'          => 'suite-client-id',
                            'client_secret'      => 'suite-client-secret',
                            'access_token'       => 'suite-access-token',
                            'refresh_token'      => 'suite-refresh-token',
                            'expire_stamp'       => time() + 3600,
                            'disable_encryption' => 'yes',
                        ],
                    ],
                ],
                'mappings' => [$sender => $connectionKey],
                'misc'     => $baseMisc($connectionKey),
                'suite_marker' => ['source' => 'update-connection', 'version' => 1],
            ];

            $model = new Settings();
            $model->saveGlobalSettings($initial);
            $before = $model->getSettings();
            $updatedConnection = $before['connections'][$connectionKey]['provider_settings'];
            $updatedConnection['sender_name'] = 'After Name';

            $model->updateConnection($sender, $updatedConnection);
            $after = $model->getSettings();
            $assertOnlyFieldChanged(
                $before,
                $after,
                'connections.' . $connectionKey . '.provider_settings.sender_name',
                'After Name',
                'updateConnection'
            );

            $model->delete($connectionKey);
            $deleted = $model->getSettings();
            FsmtpTest::assert(!isset($deleted['connections'][$connectionKey]), 'updateConnection fixture was not deleted');
            FsmtpTest::assert(!isset($deleted['mappings'][$sender]), 'updateConnection fixture mapping was not deleted');
        });
    });

    FsmtpTest::case('sender alias round-trip adds and removes one mapping and its alias without touching other connection data', function () use (
        $withOptionTransaction,
        $readSettings,
        $baseMisc
    ) {
        FsmtpTest::interceptHttp(function ($url) {
            if (strpos($url, 'https://api.tosend.com/v2/info?') === 0) {
                return [
                    'headers'  => [],
                    'body'     => wp_json_encode([
                        'account' => ['status' => 'active'],
                        'domains' => [[
                            'domain_name'        => 'example.test',
                            'verification_status' => 'verified',
                        ]],
                    ]),
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'cookies'  => [],
                    'filename' => null,
                ];
            }
            return null;
        });

        try {
            $withOptionTransaction(function () use ($readSettings, $baseMisc) {
                $sender = 'tosend-primary-' . FsmtpTest::uniq() . '@example.test';
                $alias = 'tosend-alias-' . FsmtpTest::uniq() . '@example.test';
                $connectionKey = md5($sender);
                $initial = [
                    'connections' => [
                        $connectionKey => [
                            'title' => 'Suite toSend',
                            'provider_settings' => [
                                'provider'           => 'tosend',
                                'sender_name'        => 'Suite toSend Sender',
                                'sender_email'       => $sender,
                                'force_from_name'    => 'no',
                                'force_from_email'   => 'yes',
                                'key_store'          => 'db',
                                'api_key'            => 'suite-api-key',
                                'additional_senders' => [],
                                'disable_encryption' => 'yes',
                            ],
                        ],
                    ],
                    'mappings' => [$sender => $connectionKey],
                    'misc'     => $baseMisc($connectionKey),
                    'suite_marker' => ['source' => 'sender-alias', 'version' => 1],
                ];

                FsmtpTest::assert(fluentMailSetSettings($initial), 'create sender alias connection');
                $before = $readSettings();

                $add = FsmtpTest::ajax('POST', 'settings/add_new_sender_email', [
                    'connection_id' => $connectionKey,
                    'new_sender'    => $alias,
                ]);
                FsmtpTest::assertAjaxHealthy($add, 'add sender alias');
                fluentMailGetSettings([], false);
                $afterAdd = $readSettings();

                FsmtpTest::assertSame(
                    $connectionKey,
                    isset($afterAdd['mappings'][$alias]) ? $afterAdd['mappings'][$alias] : null,
                    'added sender mapping'
                );
                // toSend records the alias on the connection too, so it can be offered as a sender.
                FsmtpTest::assertSame(
                    [$alias],
                    Arr::get($afterAdd, 'connections.' . $connectionKey . '.provider_settings.additional_senders'),
                    'alias recorded on the connection'
                );
                $withoutAlias = $afterAdd;
                unset($withoutAlias['mappings'][$alias]);
                Arr::set($withoutAlias, 'connections.' . $connectionKey . '.provider_settings.additional_senders', []);
                FsmtpTest::assertSame($before, $withoutAlias, 'sender add every other field unchanged');

                $remove = FsmtpTest::ajax('POST', 'settings/remove_sender_email', [
                    'connection_id' => $connectionKey,
                    'email'         => $alias,
                ]);
                FsmtpTest::assertAjaxHealthy($remove, 'remove sender alias');
                fluentMailGetSettings([], false);
                $afterRemove = $readSettings();
                FsmtpTest::assertSame($before, $afterRemove, 'sender remove restored the exact settings graph');

                $delete = FsmtpTest::ajax('POST', '/settings/delete', ['key' => $connectionKey]);
                FsmtpTest::assertAjaxHealthy($delete, 'delete sender alias connection');
                $deleted = $readSettings();
                FsmtpTest::assert(!isset($deleted['connections'][$connectionKey]), 'sender alias connection was not deleted');
            });

            $requests = FsmtpTest::httpRequests();
            FsmtpTest::assertSame(1, count($requests), 'toSend account-info request count');
            $requestUrl = isset($requests[0]['url']) ? $requests[0]['url'] : '';
            FsmtpTest::assertSame(
                'https://api.tosend.com/v2/info',
                strtok($requestUrl, '?'),
                'toSend account-info endpoint'
            );
        } finally {
            FsmtpTest::interceptHttp();
        }
    });

    FsmtpTest::case('notification settings update one summary field without losing channel configuration', function () use (
        $withOptionTransaction,
        $readNotificationSettings,
        $assertOnlyFieldChanged
    ) {
        $withOptionTransaction(function () use ($readNotificationSettings, $assertOnlyFieldChanged) {
            delete_option('_fluent_smtp_notify_settings');
            wp_cache_delete('_fluent_smtp_notify_settings', 'options');

            $create = FsmtpTest::ajax('POST', 'settings/notification-settings', [
                'settings' => [
                    'enabled'      => 'yes',
                    'notify_email' => 'summary-before@example.test',
                    'notify_days'  => ['Mon', 'Wed', 'Fri'],
                ],
            ]);
            FsmtpTest::assertAjaxHealthy($create, 'create notification summary settings');

            NotificationHelper::updateChannelSettings('slack', [
                'status'      => 'yes',
                'token'       => 'suite-slack-token',
                'webhook_url' => 'https://hooks.example.test/suite',
            ]);
            $before = $readNotificationSettings();

            $update = FsmtpTest::ajax('POST', 'settings/notification-settings', [
                'settings' => [
                    'enabled'      => $before['enabled'],
                    'notify_email' => 'summary-after@example.test',
                    'notify_days'  => $before['notify_days'],
                ],
            ]);
            FsmtpTest::assertAjaxHealthy($update, 'update one notification summary field');
            $after = $readNotificationSettings();

            $assertOnlyFieldChanged(
                $before,
                $after,
                'notify_email',
                'summary-after@example.test',
                'notification settings'
            );
            // The GET masks channel credentials (SecretMasker::NOTIFICATION_SECRET_FIELDS); storage keeps them.
            FsmtpTest::assertSame(SecretMasker::MASK, Arr::get($after, 'slack.token'), 'Slack token on its way to the browser');
            FsmtpTest::assertSame(SecretMasker::MASK, Arr::get($after, 'slack.webhook_url'), 'Slack webhook on its way to the browser');
            $stored = (new Settings())->notificationSettings();
            FsmtpTest::assertSame('suite-slack-token', Arr::get($stored, 'slack.token'), 'Slack token after summary save');
            FsmtpTest::assertSame(
                'https://hooks.example.test/suite',
                Arr::get($stored, 'slack.webhook_url'),
                'Slack webhook after summary save'
            );
            FsmtpTest::assert(
                in_array('slack', (array)Arr::get($after, 'active_channel', []), true),
                'Slack active channel was lost'
            );

            delete_option('_fluent_smtp_notify_settings');
            wp_cache_delete('_fluent_smtp_notify_settings', 'options');
            FsmtpTest::assertSame(
                null,
                get_option('_fluent_smtp_notify_settings', null),
                'notification fixture delete'
            );
        });
    });
    FsmtpTest::case('OAuth tokens and the SES access key are ciphertext at rest and plaintext on read', function () use (
        $withOptionTransaction,
        $baseMisc
    ) {
        $withOptionTransaction(function () use ($baseMisc) {
            $gmailSender = 'gmail-cipher-' . FsmtpTest::uniq() . '@example.test';
            $sesSender = 'ses-cipher-' . FsmtpTest::uniq() . '@example.test';
            $gmailKey = md5($gmailSender);
            $sesKey = md5($sesSender);
            $settings = [
                'connections' => [
                    $gmailKey => [
                        'title' => 'Suite Gmail',
                        'provider_settings' => [
                            'provider'      => 'gmail',
                            'sender_name'   => 'Suite Gmail Sender',
                            'sender_email'  => $gmailSender,
                            'key_store'     => 'db',
                            'client_id'     => 'suite-client-id',
                            'client_secret' => 'suite-client-secret',
                            'access_token'  => 'suite-access-token',
                            'refresh_token' => 'suite-refresh-token',
                            'expire_stamp'  => time() + 3600,
                        ],
                    ],
                    $sesKey => [
                        'title' => 'Suite SES',
                        'provider_settings' => [
                            'provider'     => 'ses',
                            'sender_name'  => 'Suite SES Sender',
                            'sender_email' => $sesSender,
                            'key_store'    => 'db',
                            'access_key'   => 'AKIASUITEACCESSKEY00',
                            'secret_key'   => 'suite-ses-secret-key',
                            'region'       => 'us-east-1',
                        ],
                    ],
                ],
                'mappings' => [$gmailSender => $gmailKey, $sesSender => $sesKey],
                'misc'     => $baseMisc($gmailKey),
            ];

            FsmtpTest::assert(fluentMailSetSettings($settings), 'settings write failed');

            wp_cache_delete('fluentmail-settings', 'options');
            $raw = get_option('fluentmail-settings');
            $rawSerialized = serialize($raw);

            FsmtpTest::assertSame(SecretMasker::ENCRYPT_VERSION, (int) Arr::get($raw, 'encrypt_version'), 'stored encryption version');
            foreach (['suite-access-token', 'suite-refresh-token', 'suite-client-secret', 'AKIASUITEACCESSKEY00', 'suite-ses-secret-key'] as $secret) {
                FsmtpTest::assert(strpos($rawSerialized, $secret) === false, 'plaintext secret at rest: ' . $secret);
            }

            $read = fluentMailGetSettings([], false);
            FsmtpTest::assertSame('suite-access-token', Arr::get($read, 'connections.' . $gmailKey . '.provider_settings.access_token'), 'decrypted access token');
            FsmtpTest::assertSame('suite-refresh-token', Arr::get($read, 'connections.' . $gmailKey . '.provider_settings.refresh_token'), 'decrypted refresh token');
            FsmtpTest::assertSame('suite-client-secret', Arr::get($read, 'connections.' . $gmailKey . '.provider_settings.client_secret'), 'decrypted client secret');
            FsmtpTest::assertSame('AKIASUITEACCESSKEY00', Arr::get($read, 'connections.' . $sesKey . '.provider_settings.access_key'), 'decrypted SES access key');
            FsmtpTest::assertSame('suite-ses-secret-key', Arr::get($read, 'connections.' . $sesKey . '.provider_settings.secret_key'), 'decrypted SES secret key');
        });
    });

    FsmtpTest::case('a blob written before tokens were encrypted reads intact and the next save encrypts it', function () use (
        $withOptionTransaction,
        $baseMisc
    ) {
        $withOptionTransaction(function () use ($baseMisc) {
            $sender = 'gmail-legacy-' . FsmtpTest::uniq() . '@example.test';
            $key = md5($sender);

            // What a 2.3.x release left in wp_options: client_secret encrypted, tokens plain, no version.
            $legacy = [
                'connections' => [
                    $key => [
                        'title' => 'Suite Legacy Gmail',
                        'provider_settings' => [
                            'provider'      => 'gmail',
                            'sender_name'   => 'Suite Legacy Sender',
                            'sender_email'  => $sender,
                            'key_store'     => 'db',
                            'client_id'     => 'suite-client-id',
                            'client_secret' => fluentMailEncryptDecrypt('suite-client-secret', 'e'),
                            'access_token'  => 'ya29.suite-legacy-access',
                            'refresh_token' => '1//suite-legacy-refresh',
                            'expire_stamp'  => time() + 3600,
                        ],
                    ],
                ],
                'mappings'    => [$sender => $key],
                'misc'        => $baseMisc($key),
                'use_encrypt' => 'yes',
                'test'        => fluentMailEncryptDecrypt('test', 'e'),
            ];
            update_option('fluentmail-settings', $legacy);

            $read = fluentMailGetSettings([], false);
            FsmtpTest::assertSame('ya29.suite-legacy-access', Arr::get($read, 'connections.' . $key . '.provider_settings.access_token'), 'legacy access token read as-is');
            FsmtpTest::assertSame('1//suite-legacy-refresh', Arr::get($read, 'connections.' . $key . '.provider_settings.refresh_token'), 'legacy refresh token read as-is');
            FsmtpTest::assertSame('suite-client-secret', Arr::get($read, 'connections.' . $key . '.provider_settings.client_secret'), 'legacy client secret decrypted');

            // What a token refresh does: Settings::updateConnection() -> fluentMailSetSettings() with the read settings.
            FsmtpTest::assert(fluentMailSetSettings($read), 'legacy blob save failed');

            wp_cache_delete('fluentmail-settings', 'options');
            $raw = get_option('fluentmail-settings');
            FsmtpTest::assertSame(SecretMasker::ENCRYPT_VERSION, (int) Arr::get($raw, 'encrypt_version'), 'rewritten blob version');
            FsmtpTest::assert(strpos(serialize($raw), 'suite-legacy') === false, 'legacy token still plaintext after rewrite');

            $reread = fluentMailGetSettings([], false);
            FsmtpTest::assertSame('ya29.suite-legacy-access', Arr::get($reread, 'connections.' . $key . '.provider_settings.access_token'), 'access token after rewrite');
            FsmtpTest::assertSame('1//suite-legacy-refresh', Arr::get($reread, 'connections.' . $key . '.provider_settings.refresh_token'), 'refresh token after rewrite');
            FsmtpTest::assertSame('suite-client-secret', Arr::get($reread, 'connections.' . $key . '.provider_settings.client_secret'), 'client secret after rewrite');

        });
    });
};
