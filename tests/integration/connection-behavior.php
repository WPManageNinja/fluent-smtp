<?php

use FluentMail\App\Hooks\Handlers\SchedulerHandler;
use FluentMail\App\Models\Settings;
use FluentMail\App\Services\ConnectionHealth;
use FluentMail\App\Services\Mailer\Providers\Factory;
use FluentMail\App\Services\Mailer\Providers\Gmail\Handler as GmailHandler;

/** Outbound Google client seam: no Guzzle request can leave the process. */
class FsmtpSuiteGoogleClient
{
    public static $tokens = [];

    public function setClientId($value) {}
    public function setClientSecret($value) {}
    public function addScope($value) {}
    public function setAccessType($value) {}
    public function setApprovalPrompt($value) {}
    public function setAccessToken($value) {}
    public function refreshToken($value) { return self::$tokens; }
}

if (!class_exists('FluentSmtpLib\\Google\\Client', false)) {
    class_alias(FsmtpSuiteGoogleClient::class, 'FluentSmtpLib\\Google\\Client');
}

class FsmtpSuiteUnmappedSettings extends Settings
{
    public function getConnection($email)
    {
        return [];
    }
}

class FsmtpSuiteFactoryWithDefault extends Factory
{
    public function getDefaultProvider()
    {
        return [
            'provider'        => 'smtp',
            'sender_email'    => 'default@example.test',
            'sender_name'     => 'Suite Default',
            'host'            => 'smtp.example.test',
            'port'            => 2525,
            'auth'            => 'no',
            'encryption'      => 'none',
            'force_from_name' => 'no',
            'force_from_email'=> 'no',
        ];
    }
}

return function () {
    $outlookSettings = function ($sender) {
        return [
            'provider'      => 'outlook',
            'sender_email'  => $sender,
            'key_store'     => 'db',
            'client_id'     => 'suite-client',
            'client_secret' => 'suite-secret',
            'access_token'  => 'expired-access-token',
            'refresh_token' => 'suite-refresh-token',
            'expire_stamp'  => time() - 60,
        ];
    };

    $gmailSettings = function ($sender) {
        return [
            'provider'      => 'gmail',
            'sender_email'  => $sender,
            'key_store'     => 'db',
            'client_id'     => 'suite-client',
            'client_secret' => 'suite-secret',
            'access_token'  => 'expired-access-token',
            'refresh_token' => 'suite-refresh-token',
            'expire_stamp'  => time() - 60,
        ];
    };

    /** Run successful renewals without persisting settings or cron events. */
    $withWriteFuses = function (callable $callback) {
        global $wpdb;

        $before = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                'fluentmail-settings'
            )
        );
        $optionFuse = function ($newValue, $oldValue) {
            return $oldValue;
        };
        $scheduleFuse = function () {
            return false;
        };

        add_filter('pre_update_option_fluentmail-settings', $optionFuse, PHP_INT_MAX, 2);
        add_filter('pre_schedule_event', $scheduleFuse, PHP_INT_MAX, 3);
        try {
            return $callback();
        } finally {
            remove_filter('pre_update_option_fluentmail-settings', $optionFuse, PHP_INT_MAX);
            remove_filter('pre_schedule_event', $scheduleFuse, PHP_INT_MAX);
            wp_cache_delete('fluentmail-settings', 'options');
            wp_cache_delete('alloptions', 'options');

            $after = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                    'fluentmail-settings'
                )
            );
            FsmtpTest::assertSame($before, $after, 'OAuth write fuse preserved FluentSMTP settings');
        }
    };

    FsmtpTest::case('unmapped sender falls back to the default provider settings shape', function () {
        $factory = new FsmtpSuiteFactoryWithDefault(
            fluentMail(),
            new FsmtpSuiteUnmappedSettings()
        );

        $handler = $factory->get('unmapped@example.test');

        FsmtpTest::assert(
            $handler instanceof \FluentMail\App\Services\Mailer\Providers\Smtp\Handler,
            'unmapped sender did not resolve the default SMTP handler'
        );
        FsmtpTest::assertSame(
            'default@example.test',
            $handler->getSetting('sender_email'),
            'default provider settings were applied as-is'
        );
    });

    FsmtpTest::case('generic provider health is healthy for valid credentials', function () {
        $result = (new ConnectionHealth())->checkConnection([
            'provider' => 'smtp',
            'host'     => 'smtp.example.test',
            'port'     => 2525,
            'auth'     => 'no',
        ]);

        FsmtpTest::assertSame(ConnectionHealth::STATUS_HEALTHY, $result['status'], 'generic health status');
        FsmtpTest::assertSame('', $result['message'], 'generic healthy message');
    });

    FsmtpTest::case('generic provider health flattens validation errors', function () {
        $result = (new ConnectionHealth())->checkConnection([
            'provider' => 'smtp',
            'auth'     => 'no',
        ]);

        FsmtpTest::assertSame(ConnectionHealth::STATUS_ERROR, $result['status'], 'generic error status');
        FsmtpTest::assert(strpos($result['message'], 'SMTP host is required') !== false, 'host error was not surfaced');
        FsmtpTest::assert(strpos($result['message'], 'SMTP port is required') !== false, 'port error was not surfaced');
    });

    FsmtpTest::case('Outlook rejected grant reaches connection health with provider detail', function () use ($outlookSettings) {
        $description = 'Suite IdP says the Microsoft refresh grant was revoked.';
        FsmtpTest::interceptHttp(function ($url) use ($description) {
            if (strpos($url, 'login.microsoftonline.com/common/oauth2/v2.0/token') !== false) {
                return [
                    'headers' => [],
                    'body' => wp_json_encode([
                        'error' => 'invalid_grant',
                        'error_description' => $description,
                    ]),
                    'response' => ['code' => 400, 'message' => 'Bad Request'],
                    'cookies' => [],
                    'filename' => null,
                ];
            }
            return null;
        });

        $result = (new ConnectionHealth())->checkConnection(
            $outlookSettings('outlook-error-' . FsmtpTest::uniq() . '@example.test')
        );

        FsmtpTest::assertSame(ConnectionHealth::STATUS_ERROR, $result['status'], 'Outlook error status');
        FsmtpTest::assert(strpos($result['message'], $description) !== false, 'Microsoft error_description did not reach caller');
        FsmtpTest::assert(strpos($result['message'], 'reconnect') !== false, 'Outlook recovery guidance did not reach caller');
        FsmtpTest::assertSame(1, count(FsmtpTest::httpRequests()), 'Outlook made one intercepted token request');
    });

    FsmtpTest::case('Outlook successful token grant reports healthy without persisting', function () use (
        $outlookSettings,
        $withWriteFuses
    ) {
        FsmtpTest::interceptHttp(function ($url) {
            if (strpos($url, 'login.microsoftonline.com/common/oauth2/v2.0/token') !== false) {
                return [
                    'headers' => [],
                    'body' => wp_json_encode([
                        'access_token' => 'suite-new-access',
                        'expires_in' => 3600,
                    ]),
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'cookies' => [],
                    'filename' => null,
                ];
            }
            return null;
        });

        $result = $withWriteFuses(function () use ($outlookSettings) {
            return (new ConnectionHealth())->checkConnection(
                $outlookSettings('outlook-healthy-' . FsmtpTest::uniq() . '@example.test')
            );
        });

        FsmtpTest::assertSame(ConnectionHealth::STATUS_HEALTHY, $result['status'], 'Outlook healthy status');
        FsmtpTest::assertSame('', $result['message'], 'Outlook healthy message');
    });

    FsmtpTest::case('Gmail handler surfaces a rejected grant description', function () use ($gmailSettings) {
        $description = 'Suite Google grant is expired or revoked.';
        FsmtpSuiteGoogleClient::$tokens = [
            'error' => 'invalid_grant',
            'error_description' => $description,
        ];

        $method = new ReflectionMethod(GmailHandler::class, 'getApiClient');
        $method->setAccessible(true);
        $result = $method->invoke(
            new GmailHandler(),
            $gmailSettings('gmail-handler-' . FsmtpTest::uniq() . '@example.test')
        );

        FsmtpTest::assert(is_wp_error($result), 'Gmail handler did not return WP_Error for rejected grant');
        FsmtpTest::assertSame($description, $result->get_error_message(), 'Gmail handler error_description');
    });

    FsmtpTest::case('Gmail rejected grant reaches connection health with provider detail', function () use ($gmailSettings) {
        $description = 'Suite Google health grant was revoked.';
        FsmtpSuiteGoogleClient::$tokens = [
            'error' => 'invalid_grant',
            'error_description' => $description,
        ];

        $result = (new ConnectionHealth())->checkConnection(
            $gmailSettings('gmail-error-' . FsmtpTest::uniq() . '@example.test')
        );

        FsmtpTest::assertSame(ConnectionHealth::STATUS_ERROR, $result['status'], 'Gmail error status');
        FsmtpTest::assertSame($description, $result['message'], 'Gmail health error_description');
    });

    FsmtpTest::case('Gmail successful token grant reports healthy without persisting', function () use (
        $gmailSettings,
        $withWriteFuses
    ) {
        FsmtpSuiteGoogleClient::$tokens = [
            'access_token'  => 'suite-new-access',
            'refresh_token' => 'suite-new-refresh',
            'expires_in'    => 3600,
        ];

        $result = $withWriteFuses(function () use ($gmailSettings) {
            return (new ConnectionHealth())->checkConnection(
                $gmailSettings('gmail-healthy-' . FsmtpTest::uniq() . '@example.test')
            );
        });

        FsmtpTest::assertSame(ConnectionHealth::STATUS_HEALTHY, $result['status'], 'Gmail healthy status');
        FsmtpTest::assertSame('', $result['message'], 'Gmail healthy message');
    });
};
