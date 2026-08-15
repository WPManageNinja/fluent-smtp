<?php

use FluentMail\App\Hooks\Handlers\SchedulerHandler;
use FluentMail\App\Hooks\Handlers\ActionsRegistrar;
use FluentMail\App\Hooks\Handlers\AdminMenuHandler;
use FluentMail\App\Models\Settings;
use FluentMail\App\Services\ConnectionHealth;
use FluentMail\App\Services\Mailer\Providers\Factory;
use FluentMail\App\Services\Mailer\Providers\Gmail\Handler as GmailHandler;
use FluentMail\App\Services\Mailer\Providers\Outlook\API as OutlookApi;
use FluentMail\App\Services\Mailer\Providers\Outlook\Handler as OutlookHandler;
use FluentMail\App\Services\Mailer\Providers\ToSend\Handler as ToSendHandler;
use FluentMail\App\Services\NotificationHelper;

/** Minimal screen stub so is_admin() reports true without defining WP_ADMIN. */
class FsmtpSuiteAdminScreen
{
    public function in_admin()
    {
        return true;
    }
}

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

/**
 * Runs the real toSend postSend() body builder with the cURL transport and the
 * logging tail replaced, so no request leaves the process and the assertions
 * are about the serialized request body alone.
 */
class FsmtpSuiteToSendProbe extends \FluentMail\App\Services\Mailer\Providers\ToSend\Handler
{
    public $capturedBody = null;

    public function runPostSend($phpMailer, $settings)
    {
        $this->phpMailer = $phpMailer;
        $this->settings = $settings;
        $this->attributes = $this->setAttributes();

        return $this->postSend();
    }

    protected function sendViaCurl($url, $jsonBody)
    {
        $this->capturedBody = $jsonBody;

        return [
            'code' => 200,
            'body' => json_encode(['message_id' => 'suite-message-id']),
        ];
    }

    public function handleResponse($response)
    {
        return $response;
    }
}

return function () {
    /** Build one toSend request body from a PHPMailer carrying custom headers. */
    $toSendBodyWithHeaders = function (array $headers) {
        FsmtpTest::requirePhpMailer();

        $phpMailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $phpMailer->CharSet = 'UTF-8';
        $phpMailer->setFrom('sender@example.test', 'Suite Sender');
        $phpMailer->addAddress('recipient@example.test');
        $phpMailer->Subject = 'Suite toSend message';
        $phpMailer->Body = 'Suite toSend body';
        foreach ($headers as $header) {
            $phpMailer->addCustomHeader($header[0], $header[1]);
        }

        $probe = new FsmtpSuiteToSendProbe();
        $probe->runPostSend($phpMailer, [
            'provider'     => 'tosend',
            'sender_email' => 'sender@example.test',
            'sender_name'  => 'Suite Sender',
            'key_store'    => 'db',
            'api_key'      => 'suite-api-key',
        ]);

        FsmtpTest::assert(!is_null($probe->capturedBody), 'toSend probe captured no request body');

        return json_decode($probe->capturedBody, true);
    };

    FsmtpTest::case('toSend serializes custom headers as an object map', function () use ($toSendBodyWithHeaders) {
        $body = $toSendBodyWithHeaders([
            ['List-Unsubscribe', '<https://example.test/unsubscribe>'],
            ['List-Unsubscribe-Post', 'List-Unsubscribe=One-Click'],
        ]);

        FsmtpTest::assert(isset($body['headers']), 'toSend request body carried no headers field');

        // A JSON object decodes to a string-keyed array; the previous
        // append-shape decoded to a list of single-key arrays instead.
        FsmtpTest::assertSame(
            ['List-Unsubscribe', 'List-Unsubscribe-Post'],
            array_keys($body['headers']),
            'toSend custom header names'
        );
        FsmtpTest::assertSame(
            '<https://example.test/unsubscribe>',
            isset($body['headers']['List-Unsubscribe']) ? $body['headers']['List-Unsubscribe'] : null,
            'toSend List-Unsubscribe value'
        );
        FsmtpTest::assertSame(
            'List-Unsubscribe=One-Click',
            isset($body['headers']['List-Unsubscribe-Post']) ? $body['headers']['List-Unsubscribe-Post'] : null,
            'toSend List-Unsubscribe-Post value'
        );
    });

    /**
     * Drive the Slack registration-return handler for one user and report
     * whether it reached the settings write.
     *
     * The handler ends in wp_safe_redirect()+die(), which would take the whole
     * runner with it, so both exits throw instead: the write fuse when the
     * settings write is reached, and a redirect fuse for the paths that finish
     * without writing. Either way control returns before die(). The settings
     * read is filtered too, so no real notification settings are touched in
     * either direction.
     */
    $runSlackReturn = function ($userId, $pendingToken, $submittedToken) {
        $optionName = '_fluent_smtp_notify_settings';
        $writeAttempted = false;

        $stored = [
            'enabled'        => 'no',
            'active_channel' => [],
            'slack'          => ['status' => 'pending', 'token' => $pendingToken, 'webhook_url' => ''],
        ];
        $readFuse = function () use ($stored) {
            return $stored;
        };
        $writeFuse = function () use (&$writeAttempted) {
            $writeAttempted = true;
            throw new RuntimeException('fsmtp-suite-slack-write');
        };
        // Priority 1 so this runs before WP-CLI's own wp_redirect handler,
        // which would otherwise exit the runner.
        $redirectFuse = function ($location) {
            throw new RuntimeException('fsmtp-suite-slack-redirect');
        };

        add_filter('pre_option_' . $optionName, $readFuse, PHP_INT_MAX);
        add_filter('pre_update_option_' . $optionName, $writeFuse, PHP_INT_MAX, 2);
        add_filter('wp_redirect', $redirectFuse, 1);

        // is_admin() consults current_screen first; a stub keeps WP_ADMIN alone.
        $screen = isset($GLOBALS['current_screen']) ? $GLOBALS['current_screen'] : null;
        $GLOBALS['current_screen'] = new FsmtpSuiteAdminScreen();

        $previousUser = get_current_user_id();
        $oldGet = $_GET;
        $oldRequest = $_REQUEST;

        try {
            wp_set_current_user($userId);

            $_GET['page'] = 'fluent-mail';
            $_REQUEST = [
                'page'          => 'fluent-mail',
                'sub_action'    => 'slack_success',
                // Created as this user: WP nonces are bound to the user id, so
                // it must verify for whoever the handler runs as.
                '_slacK_nonce'  => wp_create_nonce('fluent_smtp_slack_register_site'),
                'site_token'    => $submittedToken,
                'slack_team'    => 'suite-team',
                'slack_webhook' => 'https://hooks.slack.test/services/suite',
            ];

            // Register and invoke only this handler's own admin_init callback,
            // so no unrelated admin_init work runs inside the suite.
            $before = isset($GLOBALS['wp_filter']['admin_init'])
                ? $GLOBALS['wp_filter']['admin_init']->callbacks
                : [];

            (new AdminMenuHandler(fluentMail()))->addFluentMailMenu();

            $after = $GLOBALS['wp_filter']['admin_init']->callbacks;
            foreach ($after as $priority => $callbacks) {
                foreach ($callbacks as $id => $registered) {
                    if (isset($before[$priority][$id])) {
                        continue;
                    }
                    remove_action('admin_init', $registered['function'], $priority);
                    if ($registered['function'] instanceof Closure) {
                        try {
                            call_user_func($registered['function']);
                        } catch (RuntimeException $e) {
                            $expected = ['fsmtp-suite-slack-write', 'fsmtp-suite-slack-redirect'];
                            if (!in_array($e->getMessage(), $expected, true)) {
                                throw $e;
                            }
                        }
                    }
                }
            }
        } finally {
            $_GET = $oldGet;
            $_REQUEST = $oldRequest;
            wp_set_current_user($previousUser);
            if ($screen === null) {
                unset($GLOBALS['current_screen']);
            } else {
                $GLOBALS['current_screen'] = $screen;
            }
            remove_filter('pre_option_' . $optionName, $readFuse, PHP_INT_MAX);
            remove_filter('pre_update_option_' . $optionName, $writeFuse, PHP_INT_MAX);
            remove_filter('wp_redirect', $redirectFuse, 1);
        }

        return $writeAttempted;
    };

    FsmtpTest::case('Slack registration return refuses a user without the manage capability', function () use ($runSlackReturn) {
        $token = FsmtpTest::uniq('slack-token');
        $username = FsmtpTest::uniq('fsmtp-slack');
        $subscriberId = wp_insert_user([
            'user_login' => $username,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $username . '@example.test',
            'role'       => 'subscriber',
        ]);
        FsmtpTest::assert(!is_wp_error($subscriberId), 'could not create the suite subscriber');

        try {
            // Valid nonce and a matching token: the capability is the only
            // thing standing between this user and a rewritten Slack channel.
            $writeAttempted = $runSlackReturn($subscriberId, $token, $token);

            FsmtpTest::assertSame(
                false,
                $writeAttempted,
                'notification settings write attempted by a subscriber'
            );
        } finally {
            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            if (!is_wp_error($subscriberId) && get_user_by('ID', $subscriberId)) {
                wp_delete_user($subscriberId);
            }
        }
    });

    FsmtpTest::case('Slack registration return still completes for a manager', function () use ($runSlackReturn) {
        $token = FsmtpTest::uniq('slack-token');

        // Guards the fix itself: the capability check must not lock out the
        // administrator the flow is built for.
        $writeAttempted = $runSlackReturn(get_current_user_id(), $token, $token);

        FsmtpTest::assertSame(
            true,
            $writeAttempted,
            'notification settings write reached for a manager'
        );
    });

    FsmtpTest::case('Slack registration return rejects a token that is only loosely equal', function () use ($runSlackReturn) {
        // Two different numeric strings that PHP's == still treats as equal.
        $writeAttempted = $runSlackReturn(get_current_user_id(), '1000', '1e3');

        FsmtpTest::assertSame(
            false,
            $writeAttempted,
            'notification settings write attempted for a non-identical token'
        );
    });

    FsmtpTest::case('requesting an Outlook authorization URL stores no plaintext secret', function () {
        global $wpdb;

        $secret = FsmtpTest::uniq('outlook-secret');

        // Building the URL persists a fresh OAuth state; fuse that write so the
        // test cannot disturb a real pending authorization on this install.
        $stateFuse = function ($newValue, $oldValue) {
            return $oldValue;
        };
        add_filter('pre_update_option__fluentmail_last_generated_state', $stateFuse, PHP_INT_MAX, 2);

        try {
            $result = FsmtpTest::ajax('POST', '/settings/outlook_auth_url', [
                'connection' => [
                    'client_id'     => 'suite-client-id',
                    'client_secret' => $secret,
                    'tenant_id'     => 'common',
                    'key_store'     => 'db',
                    'sender_email'  => 'sender@example.test',
                ],
            ]);

            FsmtpTest::assertAjaxHealthy($result, 'outlook authorization url');

            // Compared as presence, not value: a failure here must not print
            // the credential it is complaining about.
            $legacy = get_option('_fluentsmtp_intended_outlook_info');
            FsmtpTest::assertSame(
                'absent',
                $legacy === false ? 'absent' : 'present',
                'legacy Outlook option after an authorization request'
            );

            // Counted, never printed: the secret must not reach any option row.
            $leaked = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s",
                '%' . $wpdb->esc_like($secret) . '%'
            ));
            FsmtpTest::assertSame(0, $leaked, 'option rows containing the client secret');
        } finally {
            remove_filter('pre_update_option__fluentmail_last_generated_state', $stateFuse, PHP_INT_MAX);
        }
    });

    FsmtpTest::case('app load removes a legacy plaintext Outlook option', function () {
        $reflection = new ReflectionMethod(ActionsRegistrar::class, 'registerHooks');
        $lines = file($reflection->getFileName());
        $source = implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));

        // A plugin update does not reliably run the activation hook, so app load
        // is the route that reaches an install which never reopens the screen.
        FsmtpTest::assert(
            strpos(preg_replace('/\s+/', '', $source), 'purgeLegacyOutlookSecret()') !== false,
            'FluentSMTP app load no longer runs the legacy Outlook purge'
        );

        $existing = get_option('_fluentsmtp_intended_outlook_info');
        add_option(
            '_fluentsmtp_intended_outlook_info',
            ['client_secret' => FsmtpTest::uniq('legacy-secret')],
            '',
            'no'
        );

        try {
            $purge = new ReflectionMethod(ActionsRegistrar::class, 'purgeLegacyOutlookSecret');
            $purge->setAccessible(true);
            $purge->invoke(new ActionsRegistrar(fluentMail()));

            $remaining = get_option('_fluentsmtp_intended_outlook_info');
            FsmtpTest::assertSame(
                'absent',
                $remaining === false ? 'absent' : 'present',
                'legacy Outlook option after the app-load purge'
            );
        } finally {
            delete_option('_fluentsmtp_intended_outlook_info');
            if ($existing !== false) {
                update_option('_fluentsmtp_intended_outlook_info', $existing);
            }
        }
    });

    FsmtpTest::case('outbound notification and provider requests verify TLS', function () {
        // These requests carry webhook secrets, Telegram/Slack registration
        // tokens, and a toSend API key. The lint gate gets the literal source
        // forms; this asserts what the transport is actually handed.
        FsmtpTest::interceptHttp(function () {
            // Satisfies both the notification success check and the toSend
            // account-info domain loop, so no path warns on the canned body.
            return [
                'headers'  => [],
                'body'     => json_encode(['success' => true, 'domains' => []]),
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
            ];
        });

        try {
            NotificationHelper::sendSlackMessage('suite message', 'https://hooks.slack.test/services/suite', true);
            NotificationHelper::sendDiscordMessage('suite message', 'https://discord.test/api/webhooks/suite', true);
            NotificationHelper::sendFailedNotificationTele(['site' => 'suite']);
            NotificationHelper::issueTelegramPinCode(['site' => 'suite']);
            (new ToSendHandler())->getAccountInfo('suite-api-key');

            $requests = FsmtpTest::httpRequests();
            FsmtpTest::assertSame(5, count($requests), 'captured outbound request count');

            foreach ($requests as $request) {
                // Query strings carry the tokens; label with the path only.
                FsmtpTest::assertSame(
                    true,
                    isset($request['args']['sslverify']) ? $request['args']['sslverify'] : null,
                    'TLS verification for ' . strtok($request['url'], '?')
                );
            }
        } finally {
            FsmtpTest::releaseHttpInterceptor();
        }
    });

    FsmtpTest::case('toSend collapses a repeated custom header to its last value', function () use ($toSendBodyWithHeaders) {
        // A header map cannot express a repeated name. Last-wins is the
        // documented trade-off and matches the Cloudflare handler.
        $body = $toSendBodyWithHeaders([
            ['X-Suite-Repeated', 'first'],
            ['X-Suite-Repeated', 'second'],
        ]);

        FsmtpTest::assertSame(
            'second',
            isset($body['headers']['X-Suite-Repeated']) ? $body['headers']['X-Suite-Repeated'] : null,
            'toSend repeated custom header value'
        );
    });

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
