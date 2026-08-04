<?php

use FluentMail\App\Hooks\Handlers\BulkSendSessionHandler;
use FluentMail\App\Hooks\Handlers\SchedulerHandler;

/** Outbound SMTP socket seam; no network connection is ever opened. */
class FsmtpSuiteSmtpConnection
{
    public $isConnected = false;

    public function connected()
    {
        return $this->isConnected;
    }
}

/** Minimum PHPMailer seam used by BulkSendSessionHandler. */
class FsmtpSuiteBulkPhpMailer
{
    public $SMTPKeepAlive = false;
    public $closeCalls = 0;
    public $smtp;

    public function __construct()
    {
        $this->smtp = new FsmtpSuiteSmtpConnection();
    }

    public function getSMTPInstance()
    {
        return $this->smtp;
    }

    public function smtpClose()
    {
        if ($this->smtp->isConnected) {
            $this->closeCalls++;
        }
        $this->smtp->isConnected = false;
    }
}

class FsmtpSuiteFailedHandler
{
    public $getPhpMailerCalls = 0;
    private $phpMailer;

    public function __construct($phpMailer = null)
    {
        $this->phpMailer = $phpMailer;
    }

    public function getPhpMailer()
    {
        $this->getPhpMailerCalls++;
        return $this->phpMailer;
    }
}

// The Google client is an outbound provider seam. Keeping the same class name
// as connection-behavior.php lets this file run both alone and in the full tier.
if (!class_exists('FsmtpSuiteGoogleClient', false)) {
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
}

if (!class_exists('FluentSmtpLib\\Google\\Client', false)) {
    class_alias(FsmtpSuiteGoogleClient::class, 'FluentSmtpLib\\Google\\Client');
}

return function () {
    /**
     * Isolate BulkSendSessionHandler's process-wide state and global mailer.
     * The production handler is exercised while only the outbound socket is fake.
     */
    $withBulkState = function (callable $callback) {
        global $phpmailer;

        $hadPhpMailer = isset($phpmailer);
        $originalPhpMailer = $hadPhpMailer ? $phpmailer : null;
        $class = new ReflectionClass(BulkSendSessionHandler::class);
        $propertyNames = [
            'active',
            'activeSince',
            'hooked',
            'connectionFingerprint',
            'connectionOpenedAt',
            'socketReused',
        ];
        $properties = [];
        $originalValues = [];

        foreach ($propertyNames as $name) {
            $property = $class->getProperty($name);
            $property->setAccessible(true);
            $properties[$name] = $property;
            $originalValues[$name] = $property->getValue(null);
        }

        $properties['active']->setValue(null, false);
        $properties['activeSince']->setValue(null, 0);
        // Avoid registering test callbacks in the process shutdown list.
        $properties['hooked']->setValue(null, true);
        $properties['connectionFingerprint']->setValue(null, null);
        $properties['connectionOpenedAt']->setValue(null, 0);
        $properties['socketReused']->setValue(null, false);

        $phpmailer = new FsmtpSuiteBulkPhpMailer();
        $keepAliveEnabled = function () {
            return true;
        };
        add_filter('fluentmail_smtp_bulk_keep_alive', $keepAliveEnabled, PHP_INT_MAX);

        try {
            return $callback($phpmailer, $properties);
        } finally {
            remove_filter('fluentmail_smtp_bulk_keep_alive', $keepAliveEnabled, PHP_INT_MAX);
            (new BulkSendSessionHandler())->endSession();

            foreach ($properties as $name => $property) {
                $property->setValue(null, $originalValues[$name]);
            }

            if ($hadPhpMailer) {
                $phpmailer = $originalPhpMailer;
            } else {
                unset($phpmailer);
            }
        }
    };

    /**
     * Supply deterministic settings while fusing every option write. This uses
     * WordPress's real option API and refreshes FluentSMTP's own static cache.
     */
    $withSettings = function (array $settings, callable $callback) {
        global $wpdb;

        $optionNames = [
            'fluentmail-settings',
            '_fluent_smtp_notify_settings',
            '_fsmtp_last_notification_sent',
            \FluentMail\App\Services\ConnectionHealth::OPTION_KEY,
        ];
        $readOptions = function () use ($wpdb, $optionNames) {
            $snapshot = [];
            foreach ($optionNames as $name) {
                $snapshot[$name] = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",
                        $name
                    ),
                    ARRAY_A
                );
            }
            return $snapshot;
        };

        $before = $readOptions();
        $settingsFilter = function () use ($settings) {
            return $settings;
        };
        $notificationFilter = function () {
            return [
                'enabled'        => 'no',
                'notify_email'   => '',
                'notify_days'    => [],
                'active_channel' => [],
            ];
        };
        $writeFuse = function ($newValue, $oldValue) {
            return $oldValue;
        };

        add_filter('pre_option_fluentmail-settings', $settingsFilter, PHP_INT_MAX);
        add_filter('pre_option__fluent_smtp_notify_settings', $notificationFilter, PHP_INT_MAX);
        foreach ($optionNames as $name) {
            add_filter('pre_update_option_' . $name, $writeFuse, PHP_INT_MAX, 2);
        }

        wp_cache_delete('fluentmail-settings', 'options');
        fluentMailGetSettings([], false);

        try {
            return $callback();
        } finally {
            remove_filter('pre_option_fluentmail-settings', $settingsFilter, PHP_INT_MAX);
            remove_filter('pre_option__fluent_smtp_notify_settings', $notificationFilter, PHP_INT_MAX);
            foreach ($optionNames as $name) {
                remove_filter('pre_update_option_' . $name, $writeFuse, PHP_INT_MAX);
                wp_cache_delete($name, 'options');
            }
            wp_cache_delete('alloptions', 'options');
            fluentMailGetSettings([], false);

            FsmtpTest::assertSame($before, $readOptions(), 'domain invariant option fuses');
        }
    };

    $baseSettings = function () {
        return [
            'connections' => [],
            'mappings'    => [],
            'misc'        => [
                'simulate_emails'         => 'yes',
                'log_emails'              => 'no',
                'log_saved_interval_days' => '0',
                'default_connection'      => '',
                'fallback_connection'     => '',
            ],
        ];
    };

    FsmtpTest::case('bulk SMTP session start and end own the kept-alive socket lifecycle', function () use ($withBulkState) {
        $withBulkState(function ($mailer) {
            $handler = new BulkSendSessionHandler();
            $mailer->smtp->isConnected = true;
            $mailer->SMTPKeepAlive = true;

            $handler->startSession();
            FsmtpTest::assert(BulkSendSessionHandler::isActive(), 'bulk session did not become active');

            $handler->endSession();
            FsmtpTest::assert(!BulkSendSessionHandler::isActive(), 'bulk session remained active after end');
            FsmtpTest::assertSame(1, $mailer->closeCalls, 'session end SMTP close count');
            FsmtpTest::assertSame(false, $mailer->SMTPKeepAlive, 'session end keep-alive flag');
        });
    });

    FsmtpTest::case('bulk SMTP session reuses only a live socket with the same connection identity', function () use ($withBulkState) {
        $withBulkState(function ($mailer) {
            $handler = new BulkSendSessionHandler();
            $connectionA = ['host' => 'smtp-a.example.test', 'port' => 2525, 'auth_key' => 'a'];
            $connectionB = ['host' => 'smtp-b.example.test', 'port' => 2525, 'auth_key' => 'b'];

            $handler->startSession();
            BulkSendSessionHandler::ensureConnectionFor($connectionA);
            $mailer->smtp->isConnected = true;

            BulkSendSessionHandler::ensureConnectionFor($connectionA);
            FsmtpTest::assertSame(0, $mailer->closeCalls, 'matching connection close count');
            FsmtpTest::assertSame(true, BulkSendSessionHandler::wasSocketReused(), 'matching socket reuse flag');

            BulkSendSessionHandler::ensureConnectionFor($connectionB);
            FsmtpTest::assertSame(1, $mailer->closeCalls, 'changed connection close count');
            FsmtpTest::assertSame(false, BulkSendSessionHandler::wasSocketReused(), 'changed connection reuse flag');
        });
    });

    FsmtpTest::case('bulk SMTP session recycles a socket at the maximum connection age', function () use ($withBulkState) {
        $withBulkState(function ($mailer, $properties) {
            $handler = new BulkSendSessionHandler();
            $connection = ['host' => 'smtp-age.example.test', 'port' => 2525, 'auth_key' => 'age'];

            $handler->startSession();
            BulkSendSessionHandler::ensureConnectionFor($connection);
            $mailer->smtp->isConnected = true;
            $properties['connectionOpenedAt']->setValue(
                null,
                time() - BulkSendSessionHandler::MAX_CONNECTION_AGE
            );

            BulkSendSessionHandler::ensureConnectionFor($connection);
            FsmtpTest::assertSame(1, $mailer->closeCalls, 'aged connection close count');
            FsmtpTest::assertSame(false, BulkSendSessionHandler::wasSocketReused(), 'aged connection reuse flag');
        });
    });

    FsmtpTest::case('failed mail without a configured fallback routes to the no-fallback action', function () use (
        $baseSettings,
        $withSettings
    ) {
        $settings = $baseSettings();
        $handler = new FsmtpSuiteFailedHandler();
        $observed = [];
        $observer = function ($logId, $failedHandler, $data) use (&$observed) {
            $observed[] = [$logId, $failedHandler, $data];
        };
        add_action('fluentmail_email_sending_failed_no_fallback', $observer, PHP_INT_MAX, 3);

        try {
            $withSettings($settings, function () use ($handler, &$observed) {
                $data = ['response' => 'suite failure'];
                $result = (new SchedulerHandler())->maybeHandleFallbackConnection(false, 987654321, $handler, $data);

                FsmtpTest::assertSame(false, $result, 'no-fallback return value');
                FsmtpTest::assertSame(0, $handler->getPhpMailerCalls, 'no-fallback mailer access count');
                FsmtpTest::assertSame(1, count($observed), 'no-fallback action count');
                $action = isset($observed[0]) ? $observed[0] : [null, null, null];
                FsmtpTest::assertSame(987654321, $action[0], 'no-fallback log ID');
                FsmtpTest::assertSame($handler, $action[1], 'no-fallback handler');
                FsmtpTest::assertSame($data, $action[2], 'no-fallback data');
            });
        } finally {
            remove_action('fluentmail_email_sending_failed_no_fallback', $observer, PHP_INT_MAX);
        }
    });

    FsmtpTest::case('configured fallback rewrites the sender and dispatches through Simulator', function () use (
        $baseSettings,
        $withSettings
    ) {
        global $wpdb;

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        }

        $fallbackEmail = 'fallback-' . FsmtpTest::uniq() . '@example.test';
        $settings = $baseSettings();
        $settings['misc']['fallback_connection'] = 'suite-fallback';
        $settings['connections']['suite-fallback'] = [
            'title' => 'Suite Fallback',
            'provider_settings' => [
                'provider'     => 'smtp',
                'sender_email' => $fallbackEmail,
            ],
        ];

        $beforeCounts = FsmtpTest::protectedTableCounts();
        $wpdb->query('START TRANSACTION');
        try {
            $table = $wpdb->prefix . 'fsmpt_email_logs';
            $logId = FsmtpFactory::insertLog($table, [
                'subject'  => 'fallback source ' . FsmtpTest::uniq(),
                'body'     => 'source body',
                'headers'  => maybe_serialize([]),
                'response' => maybe_serialize(['message' => 'primary failed']),
                'extra'    => maybe_serialize(['provider' => 'Suite Primary']),
                'status'   => 'failed',
            ]);

            $phpMailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $phpMailer->CharSet = 'UTF-8';
            $phpMailer->setFrom('primary-' . FsmtpTest::uniq() . '@example.test', 'Suite Primary');
            $phpMailer->addAddress('recipient@example.test');
            $phpMailer->Subject = 'Suite fallback message';
            $phpMailer->Body = 'Suite fallback body';
            $handler = new FsmtpSuiteFailedHandler($phpMailer);

            $withSettings($settings, function () use ($handler, $phpMailer, $logId, $fallbackEmail, $wpdb, $table) {
                $beforeRows = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
                $result = (new SchedulerHandler())->maybeHandleFallbackConnection(false, $logId, $handler, []);
                $afterRows = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
                $simulated = $wpdb->get_row("SELECT `from`, extra FROM `{$table}` ORDER BY id DESC LIMIT 1", ARRAY_A);
                $extra = maybe_unserialize($simulated['extra']);

                FsmtpTest::assertSame(true, $result, 'configured fallback result');
                FsmtpTest::assertSame(1, $handler->getPhpMailerCalls, 'configured fallback mailer access count');
                FsmtpTest::assertSame($fallbackEmail, $phpMailer->From, 'configured fallback sender');
                FsmtpTest::assertSame($beforeRows + 1, $afterRows, 'Simulator fallback fixture row count');
                FsmtpTest::assertSame('Simulator', $extra['provider'], 'fallback provider seam');
            });
        } finally {
            $wpdb->query('ROLLBACK');
        }

        FsmtpTest::assertSame($beforeCounts, FsmtpTest::protectedTableCounts(), 'fallback transaction cleanup');
    });

    FsmtpTest::case('daily health pass re-arms Gmail renewal after a failed event ends the chain', function () use (
        $baseSettings,
        $withSettings
    ) {
        $sender = 'gmail-rearm-' . FsmtpTest::uniq() . '@example.test';
        $connectionKey = md5($sender);
        $settings = $baseSettings();
        $settings['connections'][$connectionKey] = [
            'title' => 'Suite Gmail Re-arm',
            'provider_settings' => [
                'provider'      => 'gmail',
                'sender_email'  => $sender,
                'key_store'     => 'db',
                'client_id'     => 'suite-client',
                'client_secret' => 'suite-secret',
                'access_token'  => 'expired-access-token',
                'refresh_token' => 'suite-refresh-token',
                'expire_stamp'  => time() - 60,
            ],
        ];
        $settings['mappings'][$sender] = $connectionKey;
        $settings['misc']['default_connection'] = $connectionKey;

        $scheduled = [];
        $scheduleFuse = function ($pre, $event) use (&$scheduled) {
            $scheduled[] = [
                'hook'      => $event->hook,
                'timestamp' => $event->timestamp,
            ];
            return false;
        };
        add_filter('pre_schedule_event', $scheduleFuse, PHP_INT_MAX, 3);

        $beforeCron = wp_next_scheduled('fluentsmtp_renew_gmail_token');
        try {
            $withSettings($settings, function () use (&$scheduled) {
                $scheduler = new SchedulerHandler();
                FsmtpSuiteGoogleClient::$tokens = [
                    'error'             => 'invalid_grant',
                    'error_description' => 'Suite transient renewal failure',
                ];

                $scheduler->renewGmailToken();
                FsmtpTest::assertSame([], $scheduled, 'failed renewal scheduled events');

                FsmtpSuiteGoogleClient::$tokens = [
                    'access_token'  => 'suite-rearmed-access',
                    'refresh_token' => 'suite-rearmed-refresh',
                    'expires_in'    => 3600,
                ];
                $startedAt = time();
                $scheduler->handleScheduledJobs();

                FsmtpTest::assertSame(1, count($scheduled), 'daily health re-arm event count');
                $event = isset($scheduled[0]) ? $scheduled[0] : ['hook' => null, 'timestamp' => 0];
                FsmtpTest::assertSame('fluentsmtp_renew_gmail_token', $event['hook'], 'daily health re-arm hook');
                FsmtpTest::assert(
                    $event['timestamp'] >= $startedAt + 3200
                    && $event['timestamp'] <= time() + 3300,
                    'daily health re-arm timestamp was not based on the renewed token expiry'
                );
            });
        } finally {
            remove_filter('pre_schedule_event', $scheduleFuse, PHP_INT_MAX);
        }

        FsmtpTest::assertSame($beforeCron, wp_next_scheduled('fluentsmtp_renew_gmail_token'), 'OAuth cron state');
    });
};
