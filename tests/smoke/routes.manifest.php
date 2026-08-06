<?php
/**
 * All 45 routes from app/Http/routes.php.
 *
 * GET variations mirror the actual values/options in resources/admin. POST
 * payloads preserve the request shapes used by the SPA and are intentionally
 * inert so the permission runner can dispatch them behind its safety fuses.
 */

$post = function ($route, $handler, $source, array $params = []) {
    return [
        'method'  => 'POST',
        'route'   => $route,
        'handler' => $handler,
        'source'  => $source,
        'cases'   => [
            ['label' => 'SPA payload', 'params' => $params],
        ],
    ];
};

return [
    [
        'method' => 'GET', 'route' => '/', 'handler' => 'DashboardController@index',
        'source' => 'resources/admin/Modules/Dashboard/Dashboard.vue:198',
        'cases' => [['label' => 'dashboard mount', 'params' => []]],
    ],
    [
        'method' => 'GET', 'route' => '/day-time-stats', 'handler' => 'DashboardController@getDayTimeStats',
        'source' => 'resources/admin/Modules/Dashboard/Charts/ByDayTimeSending.vue:110',
        'cases' => [
            ['label' => 'last 7 days', 'params' => ['last_day' => 7]],
            ['label' => 'last 30 days', 'params' => ['last_day' => 30]],
            ['label' => 'all time', 'params' => ['last_day' => 0]],
        ],
    ],
    [
        'method' => 'GET', 'route' => 'sending_stats', 'handler' => 'DashboardController@getSendingStats',
        'source' => 'resources/admin/Modules/Dashboard/Charts/Emails.vue:29',
        'cases' => [
            ['label' => 'initial empty date range', 'params' => ['date_range' => '']],
            ['label' => 'last week', 'params' => ['date_range' => ['{7_days_ago}', '{today}']]],
            ['label' => 'last month', 'params' => ['date_range' => ['{30_days_ago}', '{today}']]],
            ['label' => 'last 3 months', 'params' => ['date_range' => ['{90_days_ago}', '{today}']]],
        ],
    ],
    [
        'method' => 'GET', 'route' => '/settings', 'handler' => 'SettingsController@index',
        'source' => 'resources/admin/Modules/Settings/Connections.vue:119',
        'cases' => [['label' => 'connections screen', 'params' => []]],
    ],
    $post('/settings/validate', 'SettingsController@validate', 'app/Http/routes.php:7', [
        'provider' => ['key' => 'smtp'],
        'sender_email' => 'invalid',
    ]),
    $post('/settings', 'SettingsController@store', 'resources/admin/Modules/Settings/ConnectionWizard.vue:194', [
        'connection_key' => '',
        'connection' => ['provider' => 'smtp', 'sender_email' => 'invalid'],
        'valid_senders' => [],
    ]),
    $post('/misc-settings', 'SettingsController@storeMiscSettings', 'resources/admin/Modules/Settings/_GeneralSettings.vue:138', [
        'settings' => [],
    ]),
    $post('/settings/delete', 'SettingsController@delete', 'resources/admin/Modules/Settings/Connections.vue:142', [
        'key' => 'fsmtp-suite-missing-connection',
    ]),
    $post('/settings/misc', 'SettingsController@storeGlobals', 'app/Http/routes.php:11', []),
    $post('/settings/test', 'SettingsController@sendTestEmil', 'resources/admin/Modules/Test/Test.vue:115', [
        'email' => 'fsmtp-suite@example.test', 'from' => '', 'isHtml' => 'true',
    ]),
    $post('/settings/subscribe', 'SettingsController@subscribe', 'resources/admin/Pieces/_Subscrbe.vue:56', [
        'email' => 'fsmtp-suite@example.test', 'display_name' => 'FluentSMTP Suite', 'share_essentials' => 'no',
    ]),
    $post('/settings/subscribe-dismiss', 'SettingsController@subscribeDismiss', 'resources/admin/Pieces/_SubscribeDismiss.vue:10'),
    [
        'method' => 'GET', 'route' => 'settings/connection_info', 'handler' => 'SettingsController@getConnectionInfo',
        'source' => 'resources/admin/Modules/Settings/ConnectionDetails.vue:66',
        'cases' => [[
            'label' => 'connection details request shape',
            'params' => ['connection_id' => '{missing_connection}'],
        ]],
    ],
    $post('settings/add_new_sender_email', 'SettingsController@addNewSenderEmail', 'resources/admin/Modules/Settings/ConnectionDetails.vue:109', [
        'connection_id' => 'fsmtp-suite-missing-connection', 'new_sender' => 'fsmtp-suite@example.test',
    ]),
    $post('settings/remove_sender_email', 'SettingsController@removeSenderEmail', 'resources/admin/Modules/Settings/ConnectionDetails.vue:136', [
        'connection_id' => 'fsmtp-suite-missing-connection', 'email' => 'fsmtp-suite@example.test',
    ]),
    [
        'method' => 'GET', 'route' => 'settings/notification-settings', 'handler' => 'SettingsController@getNotificationSettings',
        'source' => 'resources/admin/Modules/NotificationSettings/NotificationSettings.vue:40',
        'cases' => [['label' => 'notification screen mount', 'params' => []]],
    ],
    $post('settings/notification-settings', 'SettingsController@saveNotificationSettings', 'resources/admin/Modules/NotificationSettings/_EmailSummaryForm.vue:64', [
        'settings' => ['enabled' => 'no', 'notify_email' => '{site_admin}', 'notify_days' => ['Mon']],
    ]),
    [
        'method' => 'GET', 'route' => 'settings/notification-channels', 'handler' => 'SettingsController@getNotificationChannels',
        'source' => 'resources/admin/Modules/NotificationSettings/NotificationManager.vue:119',
        'cases' => [['label' => 'channel list mount', 'params' => []]],
    ],
    $post('settings/notification-channels/toggle', 'SettingsController@toggleNotificationChannel', 'resources/admin/Modules/NotificationSettings/_AlertListTable.vue:127', [
        'channel_keys' => [],
    ]),
    $post('settings/gmail_auth_url', 'SettingsController@getGmailAuthUrl', 'resources/admin/Modules/Settings/Partials/Providers/Gmail.vue:137', [
        'connection' => ['key_store' => 'db', 'client_id' => '', 'client_secret' => ''],
    ]),
    $post('settings/outlook_auth_url', 'SettingsController@getOutlookAuthUrl', 'resources/admin/Modules/Settings/Partials/Providers/Outlook.vue:131', [
        'connection' => ['key_store' => 'wp_config', 'client_id' => '', 'client_secret' => ''],
    ]),
    $post('settings/telegram/issue-pin-code', 'TelegramController@issuePinCode', 'resources/admin/Modules/NotificationSettings/_TelegramNotification.vue:82', [
        'settings' => ['user_email' => 'invalid', 'terms' => 'no'],
    ]),
    $post('settings/telegram/confirm', 'TelegramController@confirmConnection', 'resources/admin/Modules/NotificationSettings/_TelegramNotification.vue:100', [
        'site_token' => '',
    ]),
    [
        'method' => 'GET', 'route' => 'settings/telegram/info', 'handler' => 'TelegramController@getTelegramConnectionInfo',
        'source' => 'resources/admin/Modules/NotificationSettings/_TelegramConnectionInfo.vue:61',
        'cases' => [['label' => 'telegram panel mount', 'params' => []]],
    ],
    $post('settings/telegram/send-test', 'TelegramController@sendTestMessage', 'resources/admin/Modules/NotificationSettings/_ChannelActions.vue:76'),
    $post('settings/telegram/disconnect', 'TelegramController@disconnect', 'resources/admin/Modules/NotificationSettings/_ChannelActions.vue:61'),
    $post('settings/slack/register', 'SlackController@registerSite', 'resources/admin/Modules/NotificationSettings/_SlackNotification.vue:77', [
        'settings' => ['user_email' => 'invalid', 'terms' => 'no'],
    ]),
    $post('settings/slack/send-test', 'SlackController@sendTestMessage', 'resources/admin/Modules/NotificationSettings/_ChannelActions.vue:76'),
    $post('settings/slack/disconnect', 'SlackController@disconnect', 'resources/admin/Modules/NotificationSettings/_ChannelActions.vue:61'),
    $post('settings/discord/register', 'DiscordController@registerSite', 'resources/admin/Modules/NotificationSettings/_DiscordNotification.vue:73', [
        'settings' => ['webhook_url' => '', 'channel_name' => ''],
    ]),
    $post('settings/discord/send-test', 'DiscordController@sendTestMessage', 'resources/admin/Modules/NotificationSettings/_ChannelActions.vue:76'),
    $post('settings/discord/disconnect', 'DiscordController@disconnect', 'resources/admin/Modules/NotificationSettings/_ChannelActions.vue:61'),
    $post('settings/pushover/register', 'PushoverController@registerSite', 'resources/admin/Modules/NotificationSettings/_PushoverNotification.vue:73', [
        'settings' => ['api_token' => '', 'user_key' => ''],
    ]),
    $post('settings/pushover/send-test', 'PushoverController@sendTestMessage', 'resources/admin/Modules/NotificationSettings/_ChannelActions.vue:76'),
    $post('settings/pushover/disconnect', 'PushoverController@disconnect', 'resources/admin/Modules/NotificationSettings/_ChannelActions.vue:61'),
    $post('settings/gotify/register', 'GotifyController@registerSite', 'resources/admin/Modules/NotificationSettings/_GotifyNotification.vue:73', [
        'settings' => ['server_url' => '', 'app_token' => ''],
    ]),
    $post('settings/gotify/send-test', 'GotifyController@sendTestMessage', 'resources/admin/Modules/NotificationSettings/_ChannelActions.vue:76'),
    $post('settings/gotify/disconnect', 'GotifyController@disconnect', 'resources/admin/Modules/NotificationSettings/_ChannelActions.vue:61'),
    [
        'method' => 'GET', 'route' => '/logs', 'handler' => 'LoggerController@get',
        'source' => 'resources/admin/Modules/Logger/Logs.vue:210',
        'cases' => [
            ['label' => 'default table state', 'params' => ['per_page' => 10, 'page' => 1, 'status' => '', 'date_range' => [], 'search' => '']],
            ['label' => 'page 2', 'params' => ['per_page' => 10, 'page' => 2, 'status' => '', 'date_range' => [], 'search' => '']],
            ['label' => '20 rows', 'params' => ['per_page' => 20, 'page' => 1, 'status' => '', 'date_range' => [], 'search' => '']],
            ['label' => '50 rows', 'params' => ['per_page' => 50, 'page' => 1, 'status' => '', 'date_range' => [], 'search' => '']],
            ['label' => '80 rows', 'params' => ['per_page' => 80, 'page' => 1, 'status' => '', 'date_range' => [], 'search' => '']],
            ['label' => '100 rows', 'params' => ['per_page' => 100, 'page' => 1, 'status' => '', 'date_range' => [], 'search' => '']],
            ['label' => '120 rows', 'params' => ['per_page' => 120, 'page' => 1, 'status' => '', 'date_range' => [], 'search' => '']],
            ['label' => '150 rows', 'params' => ['per_page' => 150, 'page' => 1, 'status' => '', 'date_range' => [], 'search' => '']],
            ['label' => 'successful status', 'params' => ['per_page' => 10, 'page' => 1, 'status' => 'sent', 'date_range' => [], 'search' => '']],
            ['label' => 'failed status', 'params' => ['per_page' => 10, 'page' => 1, 'status' => 'failed', 'date_range' => [], 'search' => '']],
            ['label' => 'today', 'params' => ['per_page' => 10, 'page' => 1, 'status' => '', 'date_range' => ['{today}', '{today}'], 'search' => '']],
            ['label' => 'last week', 'params' => ['per_page' => 10, 'page' => 1, 'status' => '', 'date_range' => ['{7_days_ago}', '{today}'], 'search' => '']],
            ['label' => 'last month', 'params' => ['per_page' => 10, 'page' => 1, 'status' => '', 'date_range' => ['{30_days_ago}', '{today}'], 'search' => '']],
            ['label' => 'last 3 months', 'params' => ['per_page' => 10, 'page' => 1, 'status' => '', 'date_range' => ['{90_days_ago}', '{today}'], 'search' => '']],
            ['label' => 'search query', 'params' => ['per_page' => 10, 'page' => 1, 'status' => '', 'date_range' => [], 'search' => '{search}']],
            ['label' => 'combined router query', 'params' => ['per_page' => 20, 'page' => 2, 'status' => 'failed', 'date_range' => ['{30_days_ago}', '{today}'], 'search' => '{search}']],
        ],
    ],
    [
        'method' => 'GET', 'route' => '/logs/show', 'handler' => 'LoggerController@show',
        'source' => 'resources/admin/Modules/Logger/LogViewer.vue:183',
        'cases' => [
            ['label' => 'viewer opens', 'needs_log' => true, 'params' => ['id' => '{log_id}', 'dir' => null, 'query' => null, 'filter_by' => null, 'filter_by_value' => null]],
            ['label' => 'viewer next', 'needs_log' => true, 'params' => ['id' => '{log_id}', 'dir' => 'next', 'query' => null, 'filter_by' => null, 'filter_by_value' => null]],
            ['label' => 'viewer previous', 'needs_log' => true, 'params' => ['id' => '{log_id}', 'dir' => 'prev', 'query' => null, 'filter_by' => null, 'filter_by_value' => null]],
        ],
    ],
    $post('/logs/retry', 'LoggerController@retry', 'resources/admin/Modules/Logger/Logs.vue:282', [
        'id' => 2147483647, 'type' => 'retry',
    ]),
    $post('/logs/retry-bulk', 'LoggerController@retryBulk', 'resources/admin/Modules/Logger/Logs.vue:389', [
        'log_ids' => [],
    ]),
    $post('/logs/delete', 'LoggerController@delete', 'resources/admin/Modules/Logger/Logs.vue:331', [
        'id' => 2147483647,
    ]),
    $post('install_plugin', 'SettingsController@installPlugin', 'resources/admin/Modules/Misc/Support.vue:186', [
        'plugin_slug' => 'fsmtp-suite-invalid',
    ]),
    [
        'method' => 'GET', 'route' => 'docs', 'handler' => 'DashboardController@getDocs',
        'source' => 'resources/admin/Modules/Misc/Docs.vue:117',
        'cases' => [['label' => 'documentation screen', 'params' => []]],
    ],
];
