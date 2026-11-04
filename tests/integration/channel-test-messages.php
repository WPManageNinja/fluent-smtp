<?php

use FluentMail\App\Services\NotificationHelper;

/**
 * The test message the Discord, Slack and Pushover channels send.
 *
 * Driven through each channel's real send-test route. The stored settings come
 * from a pre_option filter and the endpoints from the HTTP interceptor, so
 * nothing is stored and nothing leaves the process.
 */
return function () {
    $channels = [
        'discord'  => ['status' => 'yes', 'channel_name' => 'suite', 'webhook_url' => 'https://discord.example.test/hook'],
        'slack'    => ['status' => 'yes', 'token' => 'suite-token', 'webhook_url' => 'https://slack.example.test/hook'],
        'pushover' => ['status' => 'yes', 'api_token' => 'suite-api-token', 'user_key' => 'suite-user-key'],
    ];

    /** Send one channel's test through its route and return the message text it posted. */
    $postedMessage = function ($channel) use ($channels) {
        $stored = function () use ($channel, $channels) {
            return [
                'active_channel' => [$channel],
                $channel         => $channels[$channel],
            ];
        };
        add_filter('pre_option__fluent_smtp_notify_settings', $stored, PHP_INT_MAX);
        FsmtpTest::interceptHttp(function () {
            return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => '{"status":1}'];
        });

        try {
            FsmtpTest::ajax('POST', 'settings/' . $channel . '/send-test');
            $requests = FsmtpTest::httpRequests();
        } finally {
            FsmtpTest::releaseHttpInterceptor();
            remove_filter('pre_option__fluent_smtp_notify_settings', $stored, PHP_INT_MAX);
        }

        FsmtpTest::assertSame(1, count($requests), $channel . ' test requests made');
        $body = $requests[0]['args']['body'];

        // Pushover posts a form array; Discord and Slack post JSON.
        if (is_array($body)) {
            return (string) $body['message'];
        }

        $decoded = json_decode($body, true);

        return (string) ('discord' === $channel ? $decoded['content'] : $decoded['text']);
    };

    FsmtpTest::case('every channel test sends the one translatable test sentence', function () use ($channels, $postedMessage) {
        $expected = 'Test message from ' . site_url() . '. If you can read this, the connection is working.';

        FsmtpTest::assertSame($expected, NotificationHelper::getTestMessage(), 'the shared test sentence');

        foreach (array_keys($channels) as $channel) {
            FsmtpTest::assertSame($expected, $postedMessage($channel), $channel . ' test message');
        }
    });

    /*
     * The point of the change. Japanese puts the source before "test message"
     * and ends the sentence with "。", which fragments joined around the URL in
     * code could not express.
     */
    FsmtpTest::case('a translation can move the site URL and use its own punctuation', function () {
        $translate = function ($translation, $text, $domain) {
            if ('fluent-smtp' === $domain && 'Test message from %s. If you can read this, the connection is working.' === $text) {
                return '%s からのテストメッセージです。これが読めれば接続は正常です。';
            }
            return $translation;
        };
        add_filter('gettext', $translate, 10, 3);

        try {
            $message = NotificationHelper::getTestMessage();
        } finally {
            remove_filter('gettext', $translate, 10);
        }

        FsmtpTest::assertSame(
            site_url() . ' からのテストメッセージです。これが読めれば接続は正常です。',
            $message,
            'reordered translation'
        );
    });
};
