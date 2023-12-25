<?php

namespace FluentMail\App\Services;


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
        $url = self::getTelegramServerUrl() . 'register-site';

        $response = wp_remote_post($url, [
            'body'      => $data,
            'sslverify' => false,
            'timeout'   => 50
        ]);

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

    public static function getTelegramConnectionInfo($token)
    {
        $url = self::getTelegramServerUrl() . 'get-site-info?site_token=' . $token;

        $response = wp_remote_get($url, [
            'sslverify' => false,
            'timeout'   => 50
        ]);

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
