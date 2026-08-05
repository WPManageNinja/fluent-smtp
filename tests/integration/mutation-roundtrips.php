<?php

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\NotificationHelper;
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

    $baseMisc = function ($defaultConnection = '') {
        return [
            'log_emails'              => 'yes',
            'log_saved_interval_days' => '14',
            'disable_fluentcrm_logs'  => 'no',
            'default_connection'      => $defaultConnection,
            'fallback_connection'     => '',
            'simulate_emails'         => 'yes',
            'send_as_text'            => 'no',
        ];
    };

    FsmtpTest::case('global settings round-trip preserves OAuth tokens during an unrelated misc save', function () use (
        $withOptionTransaction,
        $readSettings,
        $assertOnlyFieldChanged,
        $baseMisc
    ) {
        $withOptionTransaction(function () use ($readSettings, $assertOnlyFieldChanged, $baseMisc) {
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

            $create = FsmtpTest::ajax('POST', '/settings/misc', $initial);
            FsmtpTest::assertAjaxHealthy($create, 'create global settings');
            $before = $readSettings();

            FsmtpTest::assertSame(
                'suite-access-token',
                Arr::get($before, 'connections.' . $connectionKey . '.provider_settings.access_token'),
                'created OAuth access token'
            );
            FsmtpTest::assertSame(
                'suite-refresh-token',
                Arr::get($before, 'connections.' . $connectionKey . '.provider_settings.refresh_token'),
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
                Arr::get($after, 'connections.' . $connectionKey . '.provider_settings.access_token'),
                'OAuth access token after unrelated save'
            );
            FsmtpTest::assertSame(
                'suite-refresh-token',
                Arr::get($after, 'connections.' . $connectionKey . '.provider_settings.refresh_token'),
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

    FsmtpTest::case('sender alias round-trip adds and removes one mapping without changing connection data', function () use (
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

                $create = FsmtpTest::ajax('POST', '/settings/misc', $initial);
                FsmtpTest::assertAjaxHealthy($create, 'create sender alias connection');
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
                $withoutAlias = $afterAdd;
                unset($withoutAlias['mappings'][$alias]);
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
            FsmtpTest::assertSame('suite-slack-token', Arr::get($after, 'slack.token'), 'Slack token after summary save');
            FsmtpTest::assertSame(
                'https://hooks.example.test/suite',
                Arr::get($after, 'slack.webhook_url'),
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
};
