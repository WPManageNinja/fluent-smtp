<?php

use FluentMail\App\Hooks\Handlers\BulkSendSessionHandler;
use FluentMail\App\Hooks\Handlers\SchedulerHandler;
use FluentMail\App\Services\Mailer\BaseHandler;

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

/** Real failed-send logging path with deterministic message attributes. */
class FsmtpSuiteLogPersistenceHandler extends BaseHandler
{
    public function seed($existingRowId = null)
    {
        $this->attributes = [
            'to'          => ['recipient@example.test'],
            'from'        => 'sender@example.test',
            'subject'     => 'Log persistence invariant',
            'message'     => 'Local integration fixture',
            'attachments' => [],
            'headers'     => [],
            'extra'       => [],
        ];
        $this->settings = ['provider' => 'smtp'];
        $this->existing_row_id = $existingRowId;

        return $this;
    }
}

/**
 * A real BaseHandler whose provider call has already failed: drives the production processResponse()/writeLog() path
 * (log row + failure hook) without any provider or network involvement.
 */
class FsmtpSuiteLogWriteHandler extends \FluentMail\App\Services\Mailer\BaseHandler
{
    public function __construct($subject, $phpMailer = null)
    {
        parent::__construct();
        $this->settings = ['provider' => 'Suite Primary'];
        $this->attributes = [
            'to'          => [['email' => 'recipient@example.test', 'name' => '']],
            'from'        => 'suite-sender@example.test',
            'subject'     => $subject,
            'message'     => 'suite failed body ' . $subject,
            'attachments' => [],
            'headers'     => [],
            'extra'       => [],
        ];
        if ($phpMailer) {
            $this->setPhpMailer($phpMailer);
        }
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

    FsmtpTest::case('durable failed log insert emits the failed-send hook with its row ID', function () use (
        $baseSettings,
        $withSettings
    ) {
        global $wpdb;

        $settings = $baseSettings();
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $signals = [];
        $observer = function ($status, $logId) use (&$signals) {
            $signals[] = [$status, $logId];
            return $status;
        };

        add_filter('query', $redirect);
        add_filter('fluentmail_email_sending_failed', $observer, PHP_INT_MAX, 2);

        try {
            $result = $withSettings($settings, function () {
                return (new FsmtpSuiteLogPersistenceHandler())->seed()->processResponse([
                    'code'    => 550,
                    'message' => 'Simulated provider rejection',
                ], false);
            });
            $signal = isset($signals[0]) ? $signals[0] : [null, null];
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $signal[1]), ARRAY_A);

            FsmtpTest::assertSame(false, $result, 'durable insert send result');
            FsmtpTest::assertSame(1, count($signals), 'durable insert hook count');
            FsmtpTest::assert((int)$signal[1] > 0, 'durable insert hook row ID');
            FsmtpTest::assertSame('failed', $row['status'], 'durable insert row status');
        } finally {
            remove_filter('fluentmail_email_sending_failed', $observer, PHP_INT_MAX);
            remove_filter('query', $redirect);
        }
    });

    FsmtpTest::case('durable fallback update emits the no-fallback notification action', function () use (
        $baseSettings,
        $withSettings
    ) {
        global $wpdb;

        $settings = $baseSettings();
        $table = FsmtpFactory::emailLogTable();
        $logId = FsmtpFactory::insertLog($table, [
            'response' => maybe_serialize(['message' => 'Primary failed']),
            'extra'    => maybe_serialize(['provider' => 'Suite Primary']),
            'status'   => 'failed',
            'retries'  => 0,
        ]);
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $signals = [];
        $observer = function ($rowId) use (&$signals) {
            $signals[] = $rowId;
        };

        add_filter('query', $redirect);
        add_action('fluentmail_email_sending_failed_no_fallback', $observer, PHP_INT_MAX, 1);

        try {
            $result = $withSettings($settings, function () use ($logId) {
                return (new FsmtpSuiteLogPersistenceHandler())->seed($logId)->processResponse([
                    'code'    => 551,
                    'message' => 'Simulated fallback rejection',
                ], false);
            });
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $logId), ARRAY_A);
            $response = maybe_unserialize($row['response']);

            FsmtpTest::assertSame(false, $result, 'durable update send result');
            FsmtpTest::assertSame([$logId], array_map('intval', $signals), 'durable update notification row IDs');
            FsmtpTest::assertSame(1, (int)$row['retries'], 'durable update retry count');
            FsmtpTest::assertSame(
                'Simulated fallback rejection',
                $response['fallback_response']['message'],
                'durable update fallback response'
            );
        } finally {
            remove_action('fluentmail_email_sending_failed_no_fallback', $observer, PHP_INT_MAX);
            remove_filter('query', $redirect);
        }
    });

    FsmtpTest::case('failed log insert preserves fallback routing but suppresses notification without a durable row', function () use (
        $baseSettings,
        $withSettings
    ) {
        global $wpdb;

        $settings = $baseSettings();
        $table = $wpdb->prefix . FLUENT_MAIL_DB_PREFIX . 'email_logs';
        $missingTable = $wpdb->prefix . 'fsmtp_missing_' . strtolower(wp_generate_password(8, false, false));
        $signals = [];
        $notificationSignals = [];
        $observer = function ($status, $logId) use (&$signals) {
            $signals[] = [$status, $logId];
            return $status;
        };
        $notificationObserver = function ($rowId) use (&$notificationSignals) {
            $notificationSignals[] = $rowId;
        };
        $breakInsert = function ($query) use ($table, $missingTable) {
            if (stripos($query, 'INSERT INTO') !== false && stripos($query, $table) !== false) {
                return str_replace($table, $missingTable, $query);
            }
            return $query;
        };

        add_filter('fluentmail_email_sending_failed', $observer, PHP_INT_MAX, 2);
        add_action('fluentmail_email_sending_failed_no_fallback', $notificationObserver, PHP_INT_MAX, 1);
        add_filter('query', $breakInsert, PHP_INT_MAX);
        $wasSuppressing = $wpdb->suppress_errors();
        $wpdb->suppress_errors(true);

        try {
            $result = $withSettings($settings, function () {
                return (new FsmtpSuiteLogPersistenceHandler())->seed()->processResponse([
                    'code'    => 550,
                    'message' => 'Simulated provider rejection',
                ], false);
            });

            FsmtpTest::assertSame(false, $result, 'failed insert send result');
            FsmtpTest::assertSame([[false, 0]], $signals, 'failed insert fallback-routing signals');
            FsmtpTest::assertSame([], $notificationSignals, 'failed insert notification signals');
        } finally {
            $wpdb->suppress_errors($wasSuppressing);
            remove_filter('query', $breakInsert, PHP_INT_MAX);
            remove_action('fluentmail_email_sending_failed_no_fallback', $notificationObserver, PHP_INT_MAX);
            remove_filter('fluentmail_email_sending_failed', $observer, PHP_INT_MAX);
        }
    });

    FsmtpTest::case('failed primary insert still delivers through a configured fallback without recursion', function () use (
        $baseSettings,
        $withSettings
    ) {
        global $wpdb;

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        }

        $fallbackEmail = 'fallback-no-row-' . FsmtpTest::uniq() . '@example.test';
        $settings = $baseSettings();
        $settings['misc']['fallback_connection'] = 'suite-fallback-no-row';
        $settings['connections']['suite-fallback-no-row'] = [
            'title' => 'Suite Fallback Without Row',
            'provider_settings' => [
                'provider'     => 'smtp',
                'sender_email' => $fallbackEmail,
            ],
        ];

        $table = $wpdb->prefix . FLUENT_MAIL_DB_PREFIX . 'email_logs';
        $missingTable = $wpdb->prefix . 'fsmtp_missing_' . strtolower(wp_generate_password(8, false, false));
        $routingSignals = [];
        $notificationSignals = [];
        $routingObserver = function ($status, $logId) use (&$routingSignals) {
            $routingSignals[] = [$status, $logId];
            return $status;
        };
        $notificationObserver = function ($rowId) use (&$notificationSignals) {
            $notificationSignals[] = $rowId;
        };
        $breakInsert = function ($query) use ($table, $missingTable) {
            if (stripos($query, 'INSERT INTO') !== false && stripos($query, $table) !== false) {
                return str_replace($table, $missingTable, $query);
            }
            return $query;
        };

        $phpMailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $phpMailer->CharSet = 'UTF-8';
        $phpMailer->setFrom('primary-no-row-' . FsmtpTest::uniq() . '@example.test', 'Suite Primary');
        $phpMailer->addAddress('recipient@example.test');
        $phpMailer->Subject = 'Suite fallback without row';
        $phpMailer->Body = 'Suite fallback without row body';
        $handler = (new FsmtpSuiteLogPersistenceHandler())->seed()->setPhpMailer($phpMailer);

        add_filter('fluentmail_email_sending_failed', $routingObserver, PHP_INT_MAX, 2);
        add_action('fluentmail_email_sending_failed_no_fallback', $notificationObserver, PHP_INT_MAX, 1);
        add_filter('query', $breakInsert, PHP_INT_MAX);
        $wasSuppressing = $wpdb->suppress_errors();
        $wpdb->suppress_errors(true);

        try {
            $result = $withSettings($settings, function () use ($handler) {
                return $handler->processResponse([
                    'code'    => 550,
                    'message' => 'Simulated primary rejection',
                ], false);
            });

            FsmtpTest::assertSame(true, $result, 'fallback without row send result');
            FsmtpTest::assertSame($fallbackEmail, $phpMailer->From, 'fallback without row sender');
            FsmtpTest::assertSame([[true, 0]], $routingSignals, 'fallback without row routing signals');
            FsmtpTest::assertSame([], $notificationSignals, 'fallback without row notification signals');
        } finally {
            $wpdb->suppress_errors($wasSuppressing);
            remove_filter('query', $breakInsert, PHP_INT_MAX);
            remove_action('fluentmail_email_sending_failed_no_fallback', $notificationObserver, PHP_INT_MAX);
            remove_filter('fluentmail_email_sending_failed', $routingObserver, PHP_INT_MAX);
        }
    });

    FsmtpTest::case('fallback failure without an original row logs once and does not select fallback again', function () use (
        $baseSettings,
        $withSettings
    ) {
        global $wpdb;

        $settings = $baseSettings();
        $table = FsmtpFactory::emailLogTable();
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        $routingSignals = [];
        $notificationSignals = [];
        $routingObserver = function ($status, $logId) use (&$routingSignals) {
            $routingSignals[] = [$status, $logId];
            return $status;
        };
        $notificationObserver = function ($rowId) use (&$notificationSignals) {
            $notificationSignals[] = $rowId;
        };

        add_filter('query', $redirect);
        add_filter('fluentmail_email_sending_failed', $routingObserver, PHP_INT_MAX, 2);
        add_action('fluentmail_email_sending_failed_no_fallback', $notificationObserver, PHP_INT_MAX, 1);

        try {
            $handler = (new FsmtpSuiteLogPersistenceHandler())->seed();
            $handler->setRowId(0);
            $result = $withSettings($settings, function () use ($handler) {
                return $handler->processResponse([
                    'code'    => 551,
                    'message' => 'Simulated fallback rejection',
                ], false);
            });
            $row = $wpdb->get_row("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 1", ARRAY_A);

            FsmtpTest::assertSame(false, $result, 'fallback failure without row result');
            FsmtpTest::assertSame([], $routingSignals, 'fallback failure recursive routing signals');
            FsmtpTest::assertSame(
                [(int)$row['id']],
                array_map('intval', $notificationSignals),
                'fallback failure notification row IDs'
            );
            FsmtpTest::assertSame('failed', $row['status'], 'fallback failure durable row status');
        } finally {
            remove_action('fluentmail_email_sending_failed_no_fallback', $notificationObserver, PHP_INT_MAX);
            remove_filter('fluentmail_email_sending_failed', $routingObserver, PHP_INT_MAX);
            remove_filter('query', $redirect);
        }
    });

    FsmtpTest::case('failed fallback update does not notify without a durable row update', function () use (
        $baseSettings,
        $withSettings
    ) {
        global $wpdb;

        $settings = $baseSettings();
        $table = FsmtpFactory::emailLogTable();
        $logId = FsmtpFactory::insertLog($table, [
            'response' => maybe_serialize(['message' => 'Primary failed']),
            'extra'    => maybe_serialize(['provider' => 'Suite Primary']),
            'status'   => 'failed',
            'retries'  => 0,
        ]);
        $productionTable = $wpdb->prefix . FLUENT_MAIL_DB_PREFIX . 'email_logs';
        $missingTable = $wpdb->prefix . 'fsmtp_missing_' . strtolower(wp_generate_password(8, false, false));
        $signals = [];
        $observer = function ($rowId) use (&$signals) {
            $signals[] = $rowId;
        };
        $redirectAndBreakUpdate = function ($query) use ($productionTable, $table, $missingTable) {
            if (stripos($query, 'UPDATE') !== false && stripos($query, $productionTable) !== false) {
                return str_replace($productionTable, $missingTable, $query);
            }
            return str_replace($productionTable, $table, $query);
        };

        add_action('fluentmail_email_sending_failed_no_fallback', $observer, PHP_INT_MAX, 1);
        add_filter('query', $redirectAndBreakUpdate, PHP_INT_MAX);
        $wasSuppressing = $wpdb->suppress_errors();
        $wpdb->suppress_errors(true);

        try {
            $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $logId), ARRAY_A);
            $result = $withSettings($settings, function () use ($logId) {
                return (new FsmtpSuiteLogPersistenceHandler())->seed($logId)->processResponse([
                    'code'    => 551,
                    'message' => 'Simulated fallback rejection',
                ], false);
            });
            $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $logId), ARRAY_A);

            FsmtpTest::assertSame(false, $result, 'failed update send result');
            FsmtpTest::assertSame($before, $after, 'failed update durable row');
            FsmtpTest::assertSame([], $signals, 'failed update notification signals');
        } finally {
            $wpdb->suppress_errors($wasSuppressing);
            remove_filter('query', $redirectAndBreakUpdate, PHP_INT_MAX);
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

    // ---- failed-send logging: the failed email is recorded when the database can store it, and a failed log write
    // never changes delivery (the failure hook fires exactly as before). Fixtures live in a rolled-back transaction.
    $failedLogCase = function ($name, $failInserts, callable $assertions, ?callable $settingsFor = null) use ($baseSettings, $withSettings) {
        FsmtpTest::case($name, function () use ($name, $failInserts, $assertions, $settingsFor, $baseSettings, $withSettings) {
            global $wpdb;
            $table = $wpdb->prefix . 'fsmpt_email_logs';
            $seen = [];
            $observer = function ($logId) use (&$seen) {
                $seen[] = $logId;
            };
            add_action('fluentmail_email_sending_failed_no_fallback', $observer, PHP_INT_MAX, 1);
            $settings = $settingsFor ? $settingsFor($baseSettings()) : $baseSettings();
            $beforeCounts = FsmtpTest::protectedTableCounts();
            $wpdb->query('START TRANSACTION');
            $restore = $failInserts ? FsmtpTest::failInsertsInto($table, $failInserts) : null;
            try {
                $withSettings($settings, function () use ($assertions, $table, &$seen, &$restore) {
                    $assertions($table, $seen, $restore);
                });
            } finally {
                if ($restore) {
                    $restore();
                }
                $wpdb->query('ROLLBACK');
                remove_action('fluentmail_email_sending_failed_no_fallback', $observer, PHP_INT_MAX);
            }
            FsmtpTest::assertSame($beforeCounts, FsmtpTest::protectedTableCounts(), $name . ' cleanup');
        });
    };
    $rowFor = function ($table, $subject) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT id, status, body, extra, subject FROM `{$table}` WHERE subject LIKE %s", $wpdb->esc_like($subject) . '%'), ARRAY_A);
    };

    $failedLogCase('failed log insert is retried with a metadata-only row', 1, function ($table, &$seen) use ($rowFor) {
        $subject = 'failed log retry ' . FsmtpTest::uniq();
        $result = (new FsmtpSuiteLogWriteHandler($subject))->processResponse(['message' => 'suite failure'], false);
        $row = $rowFor($table, $subject);
        FsmtpTest::assertSame(false, $result, 'failed send result is unchanged');
        FsmtpTest::assert(!empty($row['id']), 'a failed log INSERT left no row in Failed Logs');
        $extra = $row ? maybe_unserialize($row['extra']) : [];
        FsmtpTest::assertSame('failed', $row ? $row['status'] : null, 'retried row status');
        FsmtpTest::assert($row && (string) $row['body'] === '', 'retried row stores no body');
        FsmtpTest::assert(!empty($extra['body_not_logged']), 'retried row is marked body_not_logged');
        FsmtpTest::assert(!empty($extra['log_error']), 'retried row records why the full log was not saved');
        FsmtpTest::assertSame($row ? [(int) $row['id']] : [0], array_map('intval', $seen), 'the failure hook received the retried row id');
        global $wpdb;
        $response = $row ? maybe_unserialize($wpdb->get_var($wpdb->prepare("SELECT response FROM `{$table}` WHERE id = %d", $row['id']))) : [];
        FsmtpTest::assertSame('suite failure', is_array($response) && isset($response['message']) ? $response['message'] : null, 'retried row keeps the provider error message');
    });

    $failedLogCase('an engine error on both log writes still runs fallback routing', 0, function ($table, &$seen) use ($rowFor) {
        $routed = [];
        $router = function ($status, $logId) use (&$routed) {
            $routed[] = $logId;
            return $status;
        };
        $thrower = function ($sql) use ($table) {
            if (stripos(ltrim($sql), 'INSERT') === 0 && strpos($sql, $table) !== false) {
                throw new \Error('suite engine error');
            }
            return $sql;
        };
        add_filter('fluentmail_email_sending_failed', $router, PHP_INT_MAX, 2);
        add_filter('query', $thrower, PHP_INT_MAX);
        try {
            $subject = 'failed log engine error ' . FsmtpTest::uniq();
            $result = (new FsmtpSuiteLogWriteHandler($subject))->processResponse(['message' => 'suite failure'], false);
        } finally {
            remove_filter('query', $thrower, PHP_INT_MAX);
            remove_filter('fluentmail_email_sending_failed', $router, PHP_INT_MAX);
        }
        FsmtpTest::assertSame(false, $result, 'failed send result is unchanged');
        FsmtpTest::assertSame(null, $rowFor($table, $subject), 'no row when both writes throw');
        FsmtpTest::assertSame([0], array_map('intval', $routed), 'the fallback routing filter still ran once, with no log id');
        FsmtpTest::assertSame([], $seen, 'no built-in alert without a stored row');
    });

    $failedLogCase('retry drops characters the log table rejected', 0, function ($table, &$seen, &$restore) use ($rowFor) {
        $restore = FsmtpTest::failInsertsInto($table, 5, function ($sql) {
            return (bool) preg_match('/[\x{10000}-\x{10FFFF}]/u', $sql);
        });
        $subject = 'failed log emoji ' . FsmtpTest::uniq();
        $result = (new FsmtpSuiteLogWriteHandler($subject . " \u{1F389}"))->processResponse(['message' => 'suite failure'], false);
        $row = $rowFor($table, $subject);
        FsmtpTest::assertSame(false, $result, 'failed send result is unchanged');
        FsmtpTest::assert(!empty($row['id']), 'the retry carried the same 4-byte character the table rejected');
        FsmtpTest::assertSame($subject, $row ? $row['subject'] : null, 'the stored subject has no trailing space left by the removed character');
        FsmtpTest::assertSame($row ? [(int) $row['id']] : [0], array_map('intval', $seen), 'the failure hook received the retried row id');
    });

    FsmtpTest::case('a fallback delivery marks the stored row sent when the full update fails', function () {
        global $wpdb;
        $table = $wpdb->prefix . 'fsmpt_email_logs';
        $beforeCounts = FsmtpTest::protectedTableCounts();
        $wpdb->query('START TRANSACTION');
        $restore = null;
        try {
            $logId = FsmtpFactory::insertLog($table, [
                'subject'     => 'fallback update ' . FsmtpTest::uniq(),
                'body'        => '',
                'status'      => 'failed',
                'headers'     => maybe_serialize([]),
                'attachments' => maybe_serialize([]),
                'response'    => maybe_serialize(['message' => 'primary failed']),
                'extra'       => maybe_serialize(['provider' => 'Suite Primary', 'body_not_logged' => true, 'log_error' => 'suite']),
            ]);
            $handler = new FsmtpSuiteLogWriteHandler('fallback update subject');
            $handler->setRowId($logId);
            $restore = FsmtpTest::failStatementsOn($table, 'UPDATE', 1);
            $result = $handler->processResponse(['message' => 'fallback delivered'], true);
            $restore();
            $restore = null;
            $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM `{$table}` WHERE id = %d", $logId));
            FsmtpTest::assertSame(true, $result, 'fallback result');
            FsmtpTest::assertSame('sent', $status, 'the delivered fallback still left the row marked failed');
            $response = maybe_unserialize($wpdb->get_var($wpdb->prepare("SELECT response FROM `{$table}` WHERE id = %d", $logId)));
            FsmtpTest::assert(is_array($response) && !empty($response['fallback']), 'the row notes that the fallback connection delivered it');
            FsmtpTest::assertSame('primary failed', is_array($response) && isset($response['message']) ? $response['message'] : null, 'the primary error message is kept');
        } finally {
            if ($restore) {
                $restore();
            }
            $wpdb->query('ROLLBACK');
        }
        FsmtpTest::assertSame($beforeCounts, FsmtpTest::protectedTableCounts(), 'fallback update cleanup');
    });

    $failedLogCase('fallback still runs when the log write fails', 2, function ($table, &$seen) {
        FsmtpTest::requirePhpMailer();
        $phpMailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $phpMailer->CharSet = 'UTF-8';
        $phpMailer->setFrom('primary-' . FsmtpTest::uniq() . '@example.test', 'Suite Primary');
        $phpMailer->addAddress('recipient@example.test');
        $phpMailer->Subject = 'Suite fallback after log failure';
        $phpMailer->Body = 'Suite fallback body';
        $result = (new FsmtpSuiteLogWriteHandler('fallback log failure ' . FsmtpTest::uniq(), $phpMailer))
            ->processResponse(['message' => 'primary failed'], false);
        FsmtpTest::assertSame(true, $result, 'the fallback connection still delivered');
        FsmtpTest::assertSame([], $seen, 'no "no fallback" alert when the fallback delivered');
    }, function (array $settings) {
        $settings['misc']['fallback_connection'] = 'suite-fallback';
        $settings['connections']['suite-fallback'] = [
            'title'             => 'Suite Fallback',
            'provider_settings' => ['provider' => 'smtp', 'sender_email' => 'fallback-' . FsmtpTest::uniq() . '@example.test'],
        ];
        return $settings;
    });

    FsmtpTest::case('metadata-only row is not resent', function () {
        global $wpdb;
        FsmtpTest::assertMailSimulationActive();
        $table = $wpdb->prefix . 'fsmpt_email_logs';
        $beforeCounts = FsmtpTest::protectedTableCounts();
        $wpdb->query('START TRANSACTION');
        try {
            $logId = FsmtpFactory::insertLog($table, [
                'subject' => 'metadata only ' . FsmtpTest::uniq(),
                'body'    => '',
                'status'  => 'failed',
                'headers' => maybe_serialize([]),
                'attachments' => maybe_serialize([]),
                'extra'   => maybe_serialize(['provider' => 'Suite Primary', 'body_not_logged' => true, 'log_error' => 'suite']),
            ]);
            $rowsBefore = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
            $message = null;
            try {
                (new \FluentMail\App\Models\Logger())->resendEmailFromLog($logId, 'retry');
            } catch (\Exception $e) {
                $message = $e->getMessage();
            }
            FsmtpTest::assertSame(
                __("This email can't be resent because its content could not be saved in the log.", 'fluent-smtp'),
                $message,
                'resend of a metadata-only row is refused with the agreed message'
            );
            FsmtpTest::assertSame($rowsBefore, (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"), 'nothing was sent or logged');
        } finally {
            $wpdb->query('ROLLBACK');
        }
        FsmtpTest::assertSame($beforeCounts, FsmtpTest::protectedTableCounts(), 'metadata-only resend cleanup');
    });

    FsmtpTest::case('the resend routes refuse a metadata-only row without sending', function () {
        global $wpdb;
        FsmtpTest::assertMailSimulationActive();
        $table = $wpdb->prefix . 'fsmpt_email_logs';
        $beforeCounts = FsmtpTest::protectedTableCounts();
        $wpdb->query('START TRANSACTION');
        try {
            $logId = FsmtpFactory::insertLog($table, [
                'subject'     => 'metadata only route ' . FsmtpTest::uniq(),
                'body'        => '',
                'status'      => 'failed',
                'headers'     => maybe_serialize([]),
                'attachments' => maybe_serialize([]),
                'extra'       => maybe_serialize(['provider' => 'Suite Primary', 'body_not_logged' => true, 'log_error' => 'suite']),
            ]);
            $rowsBefore = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

            $single = FsmtpTest::ajax('POST', '/logs/retry', ['id' => $logId, 'type' => 'retry']);
            FsmtpTest::assertSame(false, !empty($single['success']), 'single resend is not reported as sent');
            FsmtpTest::assertSame(
                __("This email can't be resent because its content could not be saved in the log.", 'fluent-smtp'),
                FsmtpTest::ajaxMessage($single),
                'single resend shows the agreed message'
            );

            $bulk = FsmtpTest::ajax('POST', '/logs/retry-bulk', ['log_ids' => [$logId]]);
            FsmtpTest::assertAjaxHealthy($bulk, 'bulk resend still answers');
            FsmtpTest::assertSame($rowsBefore, (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"), 'neither route sent or logged an email');
        } finally {
            $wpdb->query('ROLLBACK');
        }
        FsmtpTest::assertSame($beforeCounts, FsmtpTest::protectedTableCounts(), 'resend route cleanup');
    });
};
