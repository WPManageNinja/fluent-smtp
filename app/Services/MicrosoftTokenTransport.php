<?php

namespace FluentMail\App\Services;

/**
 * Microsoft identity transport compatibility for the OAuth token exchange.
 */
class MicrosoftTokenTransport
{
    /**
     * @var bool
     */
    private static $hookRegistered = false;

    /**
     * Register the cURL transport hook once for this request.
     *
     * @return void
     */
    public static function register()
    {
        if (self::$hookRegistered || !function_exists('add_action')) {
            return;
        }

        add_action('http_api_curl', [self::class, 'onHttpApiCurl'], 10, 3);

        self::$hookRegistered = true;
    }

    /**
     * http_api_curl action callback. Actions must not return a value, so the
     * bool-returning worker stays separate for direct testing.
     *
     * @param resource|object $handle
     * @param array $args
     * @param string $url
     * @return void
     */
    public static function onHttpApiCurl($handle, $args, $url)
    {
        self::disableAlpnForMicrosoftTokenRequest($handle, $args, $url);
    }

    /**
     * Disable ALPN only for Microsoft OAuth token requests.
     *
     * @param resource|object $handle
     * @param array $args
     * @param string $url
     * @return bool
     */
    public static function disableAlpnForMicrosoftTokenRequest($handle, $args, $url)
    {
        if (!self::isMicrosoftTokenUrl($url)) {
            return false;
        }

        if (!defined('CURLOPT_SSL_ENABLE_ALPN') || !function_exists('curl_setopt')) {
            return false;
        }

        return curl_setopt($handle, CURLOPT_SSL_ENABLE_ALPN, 0) === true;
    }

    /**
     * @param string $url
     * @return bool
     */
    public static function isMicrosoftTokenUrl($url)
    {
        $parts = function_exists('wp_parse_url') ? wp_parse_url($url) : parse_url($url);

        return is_array($parts)
            && isset($parts['host'])
            && strtolower($parts['host']) === 'login.microsoftonline.com'
            && isset($parts['path'])
            && strpos($parts['path'], '/oauth2/v2.0/token') !== false;
    }
}
