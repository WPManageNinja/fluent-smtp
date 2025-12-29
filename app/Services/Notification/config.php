<?php

return [
    'channels' => [
        'telegram' => [
            'key'         => 'telegram',
            'title'       => __('Telegram', 'fluent-smtp'),
            'logo'        => fluentMailAssetUrl('images/tele.svg'),
            'logo_name'   => 'tele.svg',
            'controller'  => 'FluentMail\App\Http\Controllers\TelegramController',
            'component'   => '_TelegramNotification',
            'info_component' => '_TelegramConnectionInfo',
            'routes'      => [
                'register'  => 'settings/telegram/issue-pin-code',
                'confirm'   => 'settings/telegram/confirm',
                'info'      => 'settings/telegram/info',
                'test'      => 'settings/telegram/send-test',
                'disconnect' => 'settings/telegram/disconnect',
            ],
        ],
        'slack' => [
            'key'         => 'slack',
            'title'       => __('Slack', 'fluent-smtp'),
            'logo'        => fluentMailAssetUrl('images/slack.svg'),
            'logo_name'   => 'slack.svg',
            'controller'  => 'FluentMail\App\Http\Controllers\SlackController',
            'component'   => '_SlackNotification',
            'info_component' => '_SlackWebhookInfo',
            'routes'      => [
                'register'  => 'settings/slack/register',
                'test'      => 'settings/slack/send-test',
                'disconnect' => 'settings/slack/disconnect',
            ],
        ],
        'discord' => [
            'key'         => 'discord',
            'title'       => __('Discord', 'fluent-smtp'),
            'logo'        => fluentMailAssetUrl('images/disc.svg'),
            'logo_name'   => 'disc.svg',
            'controller'  => 'FluentMail\App\Http\Controllers\DiscordController',
            'component'   => '_DiscordNotification',
            'info_component' => '_DiscordWebhookInfo',
            'routes'      => [
                'register'  => 'settings/discord/register',
                'test'      => 'settings/discord/send-test',
                'disconnect' => 'settings/discord/disconnect',
            ],
        ],
        'pushover' => [
            'key'         => 'pushover',
            'title'       => __('Pushover', 'fluent-smtp'),
            'logo'        => fluentMailAssetUrl('images/pushover.svg'),
            'logo_name'   => 'pushover.svg',
            'controller'  => 'FluentMail\App\Http\Controllers\PushoverController',
            'component'   => '_PushoverNotification',
            'info_component' => '_PushoverWebhookInfo',
            'routes'      => [
                'register'  => 'settings/pushover/register',
                'test'      => 'settings/pushover/send-test',
                'disconnect' => 'settings/pushover/disconnect',
            ],
        ],
    ],
];
