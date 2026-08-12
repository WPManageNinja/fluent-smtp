<?php

use FluentMail\App\Hooks\Handlers\SchedulerHandler;
use FluentMail\App\Models\Settings;
use FluentMail\App\Services\ConnectionHealth;
use FluentMail\App\Services\Mailer\Providers\Factory;
use FluentMail\App\Services\Mailer\Providers\Gmail\Handler as GmailHandler;
use FluentMail\App\Services\Mailer\Providers\Outlook\API as OutlookApi;
use FluentMail\App\Services\Mailer\Providers\Outlook\Handler as OutlookHandler;

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

/**
 * Stands in for the shared PHPMailer while the PHP mail() handler decides
 * whether to touch the transport. Sending is the one thing it must not do for
 * real, so send() only records that it was reached.
 */
class FsmtpSuiteTransportProbe
{
    public $Mailer = 'smtp';

    public $isMailCalls = 0;

    public $sendCalls = 0;

    public function isMail()
    {
        $this->Mailer = 'mail';
        ++$this->isMailCalls;
    }

    public function send()
    {
        ++$this->sendCalls;
        return true;
    }
}

/**
 * Stands in for the global PHPMailer while the bulk-session handler decides
 * whether a send may inherit a kept-alive socket. Records the close instead of
 * owning a real connection.
 */
class FsmtpSuiteSocketProbe
{
    public $Mailer = 'smtp';

    public $SMTPKeepAlive = true;

    public $closeCalls = 0;

    public function smtpClose()
    {
        ++$this->closeCalls;
    }
}

/**
 * Runs the real postSend() transport branch with the logging tail stubbed out,
 * so the assertions are about transport selection and nothing else.
 */
class FsmtpSuiteDefaultMailProbeHandler extends \FluentMail\App\Services\Mailer\Providers\DefaultMail\Handler
{
    public function runPostSend($phpMailer)
    {
        $this->phpMailer = $phpMailer;
        return $this->postSend();
    }

    protected function handleSuccess()
    {
        return 'sent';
    }

    protected function handleFailure($exception)
    {
        return 'failed';
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

    FsmtpTest::case('Outlook refreshes only an expired cached token when force is false', function () use (
        $outlookSettings,
        $withWriteFuses
    ) {
        FsmtpTest::interceptHttp(function ($url) {
            if (strpos($url, 'login.microsoftonline.com/common/oauth2/v2.0/token') !== false) {
                return [
                    'headers' => [],
                    'body' => wp_json_encode([
                        'access_token' => 'suite-refreshed-access',
                        'expires_in' => 3600,
                    ]),
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'cookies' => [],
                    'filename' => null,
                ];
            }
            return null;
        });

        $method = new ReflectionMethod(OutlookHandler::class, 'getAccessToken');
        $method->setAccessible(true);
        $handler = new OutlookHandler();
        $future = array_merge(
            $outlookSettings('outlook-future-' . FsmtpTest::uniq() . '@example.test'),
            ['access_token' => 'suite-cached-access', 'expire_stamp' => time() + 600]
        );

        $cachedToken = $method->invoke($handler, $future, false);
        FsmtpTest::assertSame('suite-cached-access', $cachedToken, 'future Outlook access token');
        FsmtpTest::assertSame(0, count(FsmtpTest::httpRequests()), 'future Outlook token request count');

        $expired = array_merge($future, [
            'sender_email' => 'outlook-expired-' . FsmtpTest::uniq() . '@example.test',
            'expire_stamp' => time() - 1,
        ]);
        $refreshedToken = $withWriteFuses(function () use ($method, $handler, $expired) {
            return $method->invoke($handler, $expired, false);
        });

        FsmtpTest::assertSame('suite-refreshed-access', $refreshedToken, 'expired Outlook access token');
        FsmtpTest::assertSame(1, count(FsmtpTest::httpRequests()), 'expired Outlook token request count');
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

    /*
     * Transport selection for the PHP mail() connection. 2.3.0 reset the
     * transport on every send from this handler, which silently overrode the
     * relay that plenty of hosts declare from phpmailer_init and broke sending
     * on those sites. The reset is still needed for one case - a fallback that
     * lands here after the SMTP provider already pointed the shared PHPMailer
     * at a relay - so both directions are pinned here.
     */
    $transportClaim = function () {
        $property = new ReflectionProperty(
            \FluentMail\App\Services\Mailer\BaseHandler::class,
            'smtpTransportClaimed'
        );
        $property->setAccessible(true);
        return (bool) $property->getValue();
    };

    FsmtpTest::case('PHP mail() connection keeps a transport declared from phpmailer_init', function () {
        \FluentMail\App\Services\Mailer\BaseHandler::forgetSmtpTransportClaim();

        $probe = new FsmtpSuiteTransportProbe();
        $result = (new FsmtpSuiteDefaultMailProbeHandler())->runPostSend($probe);

        FsmtpTest::assertSame('sent', $result, 'probe send did not complete');
        FsmtpTest::assertSame('smtp', $probe->Mailer, 'declared transport was overridden');
        FsmtpTest::assertSame(0, $probe->isMailCalls, 'isMail() ran without a claim to undo');
        FsmtpTest::assertSame(1, $probe->sendCalls, 'send() was not reached exactly once');
    });

    FsmtpTest::case('fallback to PHP mail() undoes the transport the SMTP provider claimed', function () use (
        $transportClaim
    ) {
        \FluentMail\App\Services\Mailer\BaseHandler::forgetSmtpTransportClaim();
        \FluentMail\App\Services\Mailer\BaseHandler::markSmtpTransportClaimed();

        $probe = new FsmtpSuiteTransportProbe();
        $result = (new FsmtpSuiteDefaultMailProbeHandler())->runPostSend($probe);

        FsmtpTest::assertSame('sent', $result, 'fallback probe send did not complete');
        FsmtpTest::assertSame('mail', $probe->Mailer, 'claimed SMTP transport was not reset');
        FsmtpTest::assertSame(1, $probe->isMailCalls, 'isMail() did not run for a claimed transport');
        FsmtpTest::assert(!$transportClaim(), 'the claim survived the reset that consumed it');
    });

    FsmtpTest::case('a consumed claim does not reset the transport of the next send', function () {
        \FluentMail\App\Services\Mailer\BaseHandler::forgetSmtpTransportClaim();
        \FluentMail\App\Services\Mailer\BaseHandler::markSmtpTransportClaimed();

        (new FsmtpSuiteDefaultMailProbeHandler())->runPostSend(new FsmtpSuiteTransportProbe());

        $second = new FsmtpSuiteTransportProbe();
        (new FsmtpSuiteDefaultMailProbeHandler())->runPostSend($second);

        FsmtpTest::assertSame('smtp', $second->Mailer, 'a spent claim reset a later send');
        FsmtpTest::assertSame(0, $second->isMailCalls, 'isMail() ran on a spent claim');
    });

    FsmtpTest::case('wp_mail() clears the transport claim before phpmailer_init fires', function () use (
        $transportClaim
    ) {
        FsmtpTest::assertMailSimulationActive();
        \FluentMail\App\Services\Mailer\BaseHandler::markSmtpTransportClaimed();

        $observed = null;
        $listener = function ($phpmailer) use (&$observed, $transportClaim) {
            $observed = $transportClaim();
        };
        $logFuse = function () {
            return false;
        };

        add_action('phpmailer_init', $listener);
        add_filter('fluentmail_will_log_email', $logFuse, PHP_INT_MAX);

        try {
            wp_mail(
                'transport-claim-' . FsmtpTest::uniq() . '@example.test',
                'Suite transport claim',
                'body',
                ['Content-Type: text/plain; charset=UTF-8']
            );
        } finally {
            remove_action('phpmailer_init', $listener);
            remove_filter('fluentmail_will_log_email', $logFuse, PHP_INT_MAX);
            \FluentMail\App\Services\Mailer\BaseHandler::forgetSmtpTransportClaim();
        }

        FsmtpTest::assert($observed !== null, 'phpmailer_init did not fire during wp_mail()');
        FsmtpTest::assert(
            $observed === false,
            'a stale claim was still set when phpmailer_init listeners ran'
        );
    });

    /*
     * PHPMailer's smtpConnect() adopts any connected socket without re-checking
     * Host or credentials, so a kept-alive bulk-session connection has to be
     * detached from every send this plugin's SMTP handler is not routing -
     * otherwise that mail leaves through this site's relay instead of the one
     * it was addressed to.
     */
    $withSocketProbe = function (callable $callback) {
        $probe = new FsmtpSuiteSocketProbe();
        $real = isset($GLOBALS['phpmailer']) ? $GLOBALS['phpmailer'] : null;
        $GLOBALS['phpmailer'] = $probe;

        try {
            $callback($probe);
        } finally {
            if ($real === null) {
                unset($GLOBALS['phpmailer']);
            } else {
                $GLOBALS['phpmailer'] = $real;
            }
            \FluentMail\App\Hooks\Handlers\BulkSendSessionHandler::closeConnection();
        }
    };

    FsmtpTest::case('a foreign SMTP send does not inherit the kept-alive socket', function () use (
        $withSocketProbe
    ) {
        $withSocketProbe(function ($probe) {
            $probe->Mailer = 'smtp';
            $probe->SMTPKeepAlive = true;

            \FluentMail\App\Hooks\Handlers\BulkSendSessionHandler::releaseForeignSend();

            FsmtpTest::assertSame(1, $probe->closeCalls, 'the kept-alive socket was left attached');
            FsmtpTest::assertSame(false, $probe->SMTPKeepAlive, 'keep-alive stayed on for a foreign send');
        });
    });

    FsmtpTest::case('releasing is a no-op when the send is not going over SMTP', function () use (
        $withSocketProbe
    ) {
        $withSocketProbe(function ($probe) {
            $probe->Mailer = 'mail';
            $probe->SMTPKeepAlive = true;

            \FluentMail\App\Hooks\Handlers\BulkSendSessionHandler::releaseForeignSend();

            FsmtpTest::assertSame(0, $probe->closeCalls, 'a PHP mail() send closed an SMTP socket');
        });
    });

    /*
     * Single-tenant Microsoft 365. The tenant is interpolated into the
     * authority that receives the client secret, so the shapes it accepts are
     * pinned here alongside the behaviour.
     */
    $outlookAuthority = function ($tenant) {
        $captured = null;
        $capture = function ($preempt, $args, $url) use (&$captured) {
            if ($preempt !== false) {
                return $preempt;
            }
            $captured = $url;
            return [
                'headers'  => [],
                'body'     => '{"access_token":"a","refresh_token":"r","expires_in":3599}',
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        };

        add_filter('pre_http_request', $capture, 10, 3);

        try {
            $api = new OutlookApi('suite-client', 'suite-secret', $tenant);
            $api->sendTokenRequest('refresh_token', ['refresh_token' => 'suite-refresh']);
            $authorize = $api->getAuthUrl();
        } finally {
            remove_filter('pre_http_request', $capture, 10);
        }

        return [
            'token'     => $captured,
            'authorize' => $authorize,
        ];
    };

    FsmtpTest::case('an Outlook connection with no tenant still uses the common authority', function () use (
        $outlookAuthority
    ) {
        $urls = $outlookAuthority('');

        FsmtpTest::assertSame(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            $urls['token'],
            'an upgraded connection changed authority'
        );
        FsmtpTest::assert(
            strpos($urls['authorize'], 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize') === 0,
            'the authorize URL changed for an upgraded connection'
        );
    });

    FsmtpTest::case('a configured tenant scopes both the authorize and token authority', function () use (
        $outlookAuthority
    ) {
        foreach (['aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'organizations', 'contoso.onmicrosoft.com'] as $tenant) {
            $urls = $outlookAuthority($tenant);

            FsmtpTest::assertSame(
                'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/token',
                $urls['token'],
                'token authority for ' . $tenant
            );
            FsmtpTest::assert(
                strpos($urls['authorize'], 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/authorize') === 0,
                'authorize authority for ' . $tenant
            );
        }
    });

    FsmtpTest::case('a tenant that could redirect the credential exchange is refused', function () use (
        $outlookAuthority
    ) {
        $hostile = [
            'evil.test/x/oauth2/v2.0/token?a=',
            'https://evil.test',
            '../../evil',
            'someone@evil.test',
            'tenant id with spaces',
            "guid\nHost: evil.test",
        ];

        foreach ($hostile as $tenant) {
            FsmtpTest::assert(
                !OutlookApi::isValidTenant($tenant),
                'validation accepted a hostile tenant: ' . $tenant
            );

            // Second gate: even stored, it must never steer the request.
            $urls = $outlookAuthority($tenant);
            FsmtpTest::assertSame(
                'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                $urls['token'],
                'a hostile tenant reached the token URL: ' . $tenant
            );
            FsmtpTest::assertSame(
                'login.microsoftonline.com',
                wp_parse_url($urls['authorize'], PHP_URL_HOST),
                'a hostile tenant moved the authorize host: ' . $tenant
            );
        }
    });

    FsmtpTest::case('saving an Outlook connection rejects an invalid tenant', function () {
        $connection = [
            'key_store'     => 'db',
            'provider'      => 'outlook',
            'sender_email'  => 'outlook-tenant-' . FsmtpTest::uniq() . '@example.test',
            'client_id'     => 'suite-client',
            'client_secret' => 'suite-secret',
            'tenant_id'     => 'https://evil.test',
            'access_token'  => 'suite-access',
        ];

        $errors = null;

        try {
            (new OutlookHandler())->validateProviderInformation($connection);
        } catch (\FluentMail\Includes\Support\ValidationException $e) {
            $errors = $e->errors();
        }

        FsmtpTest::assert(is_array($errors), 'an invalid tenant saved without complaint');
        FsmtpTest::assert(isset($errors['tenant_id']), 'the rejection was not reported against tenant_id');
    });

    FsmtpTest::case('a non-Outlook stored connection never triggers a tenant re-auth prompt', function () {
        $method = new ReflectionMethod(OutlookHandler::class, 'tenantChanged');
        $method->setAccessible(true);

        // The suite's own connection is the Simulator, so a stored connection
        // of another provider must not be read as a tenant that moved.
        $changed = $method->invoke(new OutlookHandler(), [
            'sender_email' => 'fsmtp-suite-safety@example.test',
            'tenant_id'    => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        FsmtpTest::assertSame(false, $changed, 'a non-Outlook connection was treated as a tenant change');
    });
};
