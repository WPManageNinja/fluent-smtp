<?php

namespace FluentMail\App\Services;


use FluentMail\App\Models\Settings;
use FluentMail\Includes\Support\Arr;

class NotificationHelper
{
    public static function getRemoteServerUrl()
    {
        if (defined('FLUENTSMTP_SERVER_REMOTE_SERVER_URL')) {
            return FLUENTSMTP_SERVER_REMOTE_SERVER_URL;
        }

        return 'https://fluentsmtp.com/wp-json/fluentsmtp_notify/v1/';
    }

    public static function issueTelegramPinCode($data)
    {
        return self::sendTeleRequest('register-site', $data, 'POST');
    }

    public static function registerSlackSite($data)
    {
        return self::sendSlackRequest('register-site', $data, 'POST');
    }

    public static function getTelegramConnectionInfo($token)
    {
        return self::sendTeleRequest('get-site-info', [], 'GET', $token);
    }

    public static function sendTestTelegramMessage($token = '')
    {
        if (!$token) {
            $settings = (new Settings())->notificationSettings();
            $token = Arr::get($settings, 'telegram.token');
        }

        return self::sendTeleRequest('send-test', [], 'POST', $token);
    }

    public static function disconnectTelegram($token)
    {
        self::sendTeleRequest('disconnect', [], 'POST', $token);

        $settings = (new Settings())->notificationSettings();

        $settings['telegram'] = [
            'status' => 'no',
            'token'  => ''
        ];
        $settings['active_channel'] = '';

        update_option('_fluent_smtp_notify_settings', $settings, false);

        return true;
    }

    public static function getTelegramBotTokenId()
    {
        static $token = null;

        if ($token !== null) {
            return $token;
        }

        $settings = (new Settings())->notificationSettings();

        $token = Arr::get($settings, 'telegram.token', false);

        if (!$token) {
            $token = false;
        }

        return $token;
    }

    public static function getSlackWebhookUrl()
    {
        static $url = null;

        if ($url !== null) {
            return $url;
        }

        $settings = (new Settings())->notificationSettings();

        $url = Arr::get($settings, 'slack.webhook_url');

        if (!$url) {
            $url = false;
        }

        return $url;
    }

    public static function sendFailedNotificationTele($data)
    {
        wp_remote_post(self::getRemoteServerUrl() . 'telegram/send-failed-notification', array(
            'timeout'   => 0.01,
            'blocking'  => false,
            'body'      => $data,
            'cookies'   => false,
            'sslverify' => false,
        ));

        return true;
    }

    private static function sendTeleRequest($route, $data = [], $method = 'POST', $token = '')
    {
        $url = self::getRemoteServerUrl() . 'telegram/' . $route;

        if ($token) {
            $url .= '?site_token=' . $token;
        }

        if ($method == 'POST') {
            $response = wp_remote_post($url, [
                'body'      => $data,
                'sslverify' => false,
                'timeout'   => 50
            ]);
        } else {
            $response = wp_remote_get($url, [
                'sslverify' => false,
                'timeout'   => 50
            ]);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $responseCode = wp_remote_retrieve_response_code($response);

        $body = wp_remote_retrieve_body($response);
        $responseData = json_decode($body, true);

        if (!$responseData || empty($responseData['success']) || $responseCode !== 200) {
            return new \WP_Error('invalid_data', 'Something went wrong', $responseData);
        }

        return $responseData;
    }

    private static function sendSlackRequest($route, $data = [], $method = 'POST', $token = '')
    {
        $url = self::getRemoteServerUrl() . 'slack/' . $route;

        if ($token) {
            $url .= '?site_token=' . $token;
        }

        if ($method == 'POST') {
            $response = wp_remote_post($url, [
                'body'      => $data,
                'sslverify' => false,
                'timeout'   => 50
            ]);
        } else {
            $response = wp_remote_get($url, [
                'sslverify' => false,
                'timeout'   => 50
            ]);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $responseCode = wp_remote_retrieve_response_code($response);

        $body = wp_remote_retrieve_body($response);

        $responseData = json_decode($body, true);

        if (!$responseData || empty($responseData['success']) || $responseCode !== 200) {
            return new \WP_Error('invalid_data', 'Something went wrong', $responseData);
        }

        return $responseData;
    }

    public static function sendSlackMessage($message, $webhookUrl, $blocking = false)
    {

        if(is_array($message)) {
            $body = wp_json_encode($message);
        } else {
            $body = wp_json_encode(array('text' => $message));
        }

        $args = array(
            'body'        => $body,
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'cookies'     => null,
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => false,
            'data_format' => 'body',
        );

        if (!$blocking) {
            $args['blocking'] = false;
            $args['timeout'] = 0.01;
        }

        $response = wp_remote_post($webhookUrl, $args);

        if (!$blocking) {
            return true;
        }

        if (is_wp_error($response)) {
            return $response;
        }


        $body = wp_remote_retrieve_body($response);


        return json_decode($body, true);
    }

    public static function sendDiscordMessage($message, $webhookUrl, $blocking = false)
    {
        $body = wp_json_encode(array(
            'content'  => $message,
            'username' => 'FluentSMTP'
        ));

        $args = array(
            'body'        => $body,
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => false,
            'data_format' => 'body',
        );

        if (!$blocking) {
            $args['blocking'] = false;
            $args['timeout'] = 0.01;
        }

        $response = wp_remote_post($webhookUrl, $args);

        if (!$blocking) {
            return true;
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public static function getActiveChannelSettings()
    {
        static $channel = null;

        if ($channel !== null) {
            return $channel;
        }

        $settings = (new Settings())->notificationSettings();

        $activeChannel = Arr::get($settings, 'active_channel', '');

        if (!$activeChannel) {
            $channel = false;
            return $channel;
        }

        $channelSettings = Arr::get($settings, $activeChannel, []);

        if (!$channelSettings || empty($channelSettings['status']) || $channelSettings['status'] != 'yes') {
            $channel = false;
            return $channel;
        }

        $channel = $channelSettings;
        $channel['driver'] = $activeChannel;

        return $channel;
    }

    public static function formatSlackMessageBlock($handler, $logData = [])
    {
        $sendingTo = maybe_unserialize(Arr::get($logData, 'to'));

        if (is_array($sendingTo)) {
            $sendingTo = Arr::get($sendingTo, '0.email', '');
        }

        if (is_array($sendingTo) || !$sendingTo) {
            $sendingTo = Arr::get($logData, 'to');
        }

        $heading = sprintf(__('[%s] Failed to send email', 'fluent-smtp'), get_bloginfo('name'));

        return [
            'text'   => $heading,
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type'  => 'plain_text',
                        'text'  => $heading,
                        "emoji" => true
                    ]
                ],
                [
                    'type'   => 'section',
                    'fields' => [
                        [
                            'type' => "mrkdwn",
                            'text' => "*Website URL:*\n " . site_url()
                        ],
                        [
                            'type' => "mrkdwn",
                            'text' => "*Sending Driver:*\n " . strtoupper($handler->getSetting('provider'))
                        ],
                        [
                            'type' => "mrkdwn",
                            'text' => "*To Email Address:*\n " . $sendingTo
                        ],
                        [
                            'type' => "mrkdwn",
                            'text' => "*Email Subject:*\n " . Arr::get($logData, 'subject')
                        ]
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => "mrkdwn",
                        'text' => "*Error Message:*\n ```" . self::getErrorMessageFromResponse(maybe_unserialize(Arr::get($logData, 'response'))) . "```"
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => "mrkdwn",
                        'text' => "<" . admin_url('options-general.php?page=fluent-mail#/logs?per_page=10&page=1&status=failed&search=') . "|View Failed Email(s)>"
                    ]
                ]
            ]
        ];
    }

    public static function formatDiscordMessageBlock($handler, $logData = [])
    {
        $sendingTo = maybe_unserialize(Arr::get($logData, 'to'));

        if (is_array($sendingTo)) {
            $sendingTo = Arr::get($sendingTo, '0.email', '');
        }

        if (is_array($sendingTo) || !$sendingTo) {
            $sendingTo = Arr::get($logData, 'to');
        }

        $heading = sprintf(__('[%s] Failed to send email', 'fluent-smtp'), get_bloginfo('name'));

        $content = '## ' . $heading . "\n";
        $content .= __('**Website URL:** ', 'fluent-smtp') . site_url() . "\n";
        $content .= __('**Sending Driver:** ', 'fluent-smtp') . strtoupper($handler->getSetting('provider')) . "\n";
        $content .= __('**To Email Address:** ', 'fluent-smtp') . $sendingTo . "\n";
        $content .= __('**Email Subject:** ', 'fluent-smtp') . Arr::get($logData, 'subject') . "\n";
        $content .= __('**Error Message:** ```', 'fluent-smtp') . self::getErrorMessageFromResponse(maybe_unserialize(Arr::get($logData, 'response'))) . "```\n";
        $content .= __('[View Failed Email(s)](', 'fluent-smtp') . admin_url('options-general.php?page=fluent-mail#/logs?per_page=10&page=1&status=failed&search=') . ')';

        return $content;
    }

    public static function getErrorMessageFromResponse($response)
    {
        if (!$response || !is_array($response)) {
            return '';
        }

        if (!empty($response['fallback_response']['message'])) {
            $message = $response['fallback_response']['message'];
        } else {
            $message = Arr::get($response, 'message');
        }

        if (!$message) {
            return '';
        }

        if (!is_string($message)) {
            $message = json_encode($message);
        }

        return $message;
    }
}
