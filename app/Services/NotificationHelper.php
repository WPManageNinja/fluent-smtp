<?php

namespace FluentMail\App\Services;


use FluentMail\App\Models\Settings;

class NotificationHelper
{
    public static function getTelegramServerUrl()
    {
        if (defined('FLUENTSMTP_SERVER_TELEGRAM_SERVER_URL')) {
            return FLUENTSMTP_SERVER_TELEGRAM_SERVER_URL;
        }

        return 'https://fluentsmtp.com/wp-json/fluentsmtp_notify/v1/telegram/';
    }

    public static function issueTelegramPinCode($data)
    {
        return self::sendTeleRequest('register-site', $data, 'POST');
    }

    public static function getTelegramConnectionInfo($token)
    {
        return self::sendTeleRequest('get-site-info', [], 'GET', $token);
    }

    public static function sendTestTelegramMessage($token = '')
    {
        if (!$token) {
            $settings = (new Settings())->notificationSettings();
            $token = $settings['telegram_notify_token'];
        }

        return self::sendTeleRequest('send-test', [], 'POST', $token);
    }

    public static function disconnectTelegram($token)
    {
        self::sendTeleRequest('disconnect', [], 'POST', $token);

        $settings = (new Settings())->notificationSettings();
        $settings['telegram_notify_status'] = 'no';
        $settings['telegram_notify_token'] = '';
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

        if (empty($settings['telegram_notify_token'])) {
            $token = false;
            return $token;
        }

        $token = $settings['telegram_notify_token'];

        return $token;
    }

    public static function sendFailedNotificationTele($data)
    {
        wp_remote_post(self::getTelegramServerUrl() . 'send-failed-notification', array(
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
        $url = self::getTelegramServerUrl() . $route;

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

}
