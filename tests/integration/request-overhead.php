<?php

use FluentMail\App\Hooks\Handlers\ActionsRegistrar;
use FluentMail\App\Hooks\Handlers\AdminMenuHandler;
use FluentMail\App\Services\Mailer\Manager;

/** Minimal screen stub so is_admin() reports true without defining WP_ADMIN. */
class FsmtpOverheadAdminScreen
{
    public function in_admin()
    {
        return true;
    }
}

/** Stands in for the Manager singleton and records whether anything resolved it. */
class FsmtpOverheadManagerSpy extends Manager
{
    public static $used = false;

    public function __construct()
    {
    }

    public function getConfig($key = null, $default = null)
    {
        static::$used = true;
        return parent::getConfig($key, $default);
    }

    public function getSettings($key = null, $default = null)
    {
        static::$used = true;
        return parent::getSettings($key, $default);
    }
}

/**
 * Work FluentSMTP does on every request, whether or not it sends mail.
 *
 * The legacy Outlook purge must not query the database on an ordinary
 * front-end request once the option is gone, and the admin-bar simulation
 * check must not build the mailer Manager.
 */
return function () {
    $legacyKey = '_fluentsmtp_intended_outlook_info';

    /** Run $callback with the legacy row stored as given, then restore the original row. */
    $withLegacyRow = function ($autoload, callable $callback) use ($legacyKey) {
        $existing = get_option($legacyKey);
        delete_option($legacyKey);
        if ($autoload !== null) {
            add_option($legacyKey, ['client_secret' => FsmtpTest::uniq('legacy-secret')], '', $autoload);
        }
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
        wp_load_alloptions();

        try {
            $callback();
        } finally {
            delete_option($legacyKey);
            if ($existing !== false) {
                update_option($legacyKey, $existing);
            }
        }
    };

    /** Run $callback inside a simulated wp-admin request. */
    $inAdmin = function (callable $callback) {
        $screen = isset($GLOBALS['current_screen']) ? $GLOBALS['current_screen'] : null;
        $GLOBALS['current_screen'] = new FsmtpOverheadAdminScreen();
        try {
            $callback();
        } finally {
            if ($screen === null) {
                unset($GLOBALS['current_screen']);
            } else {
                $GLOBALS['current_screen'] = $screen;
            }
        }
    };

    /** The app-load purge as registerHooks() runs it. */
    $appLoadPurge = function () {
        $registrar = new ActionsRegistrar(fluentMail());
        $gate = new ReflectionMethod(ActionsRegistrar::class, 'shouldPurgeLegacyOutlookSecret');
        $gate->setAccessible(true);
        if ($gate->invoke($registrar)) {
            $purge = new ReflectionMethod(ActionsRegistrar::class, 'purgeLegacyOutlookSecret');
            $purge->setAccessible(true);
            $purge->invoke($registrar);
        }
    };

    /** Count the queries $callback sends whose SQL names the legacy option. */
    $legacyQueries = function (callable $callback) use ($legacyKey) {
        $count = 0;
        $counter = function ($sql) use (&$count, $legacyKey) {
            if (strpos($sql, $legacyKey) !== false) {
                $count++;
            }
            return $sql;
        };
        add_filter('query', $counter, PHP_INT_MAX);
        try {
            $callback();
        } finally {
            remove_filter('query', $counter, PHP_INT_MAX);
        }
        return $count;
    };

    $isPresent = function () use ($legacyKey) {
        global $wpdb;
        $found = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
            $legacyKey
        ));
        return $found ? 'present' : 'absent';
    };

    FsmtpTest::case('front-end app load sends no legacy Outlook query once the option is gone', function () use ($withLegacyRow, $appLoadPurge, $legacyQueries) {
        $withLegacyRow(null, function () use ($appLoadPurge, $legacyQueries) {
            FsmtpTest::assertSame(0, $legacyQueries($appLoadPurge), 'legacy Outlook queries on a front-end load');
        });
    });

    FsmtpTest::case('front-end app load purges an autoloaded legacy Outlook option', function () use ($withLegacyRow, $appLoadPurge, $isPresent) {
        $withLegacyRow('yes', function () use ($appLoadPurge, $isPresent) {
            $appLoadPurge();
            FsmtpTest::assertSame('absent', $isPresent(), 'autoloaded legacy Outlook option after a front-end load');
        });
    });

    FsmtpTest::case('admin load purges a legacy Outlook option that is not autoloaded', function () use ($withLegacyRow, $appLoadPurge, $isPresent, $inAdmin) {
        $withLegacyRow('no', function () use ($appLoadPurge, $isPresent, $inAdmin) {
            $appLoadPurge();
            FsmtpTest::assertSame('present', $isPresent(), 'non-autoloaded legacy Outlook option after a front-end load');

            $inAdmin($appLoadPurge);
            FsmtpTest::assertSame('absent', $isPresent(), 'non-autoloaded legacy Outlook option after an admin load');
        });
    });

    FsmtpTest::case('cron load purges a legacy Outlook option that is not autoloaded', function () use ($withLegacyRow, $appLoadPurge, $isPresent) {
        $withLegacyRow('no', function () use ($appLoadPurge, $isPresent) {
            add_filter('wp_doing_cron', '__return_true', PHP_INT_MAX);
            try {
                $appLoadPurge();
            } finally {
                remove_filter('wp_doing_cron', '__return_true', PHP_INT_MAX);
            }
            FsmtpTest::assertSame('absent', $isPresent(), 'non-autoloaded legacy Outlook option after a cron load');
        });
    });

    FsmtpTest::case('admin load still checks for the legacy Outlook option', function () use ($withLegacyRow, $appLoadPurge, $legacyQueries, $inAdmin) {
        $withLegacyRow(null, function () use ($appLoadPurge, $legacyQueries, $inAdmin) {
            $queries = 0;
            $inAdmin(function () use ($appLoadPurge, $legacyQueries, &$queries) {
                $queries = $legacyQueries($appLoadPurge);
            });
            FsmtpTest::assertSame(1, $queries, 'legacy Outlook queries on an admin load');
        });
    });

    /** Call addSimulationBar() as $userId with the Manager singleton swapped for a spy. */
    $renderSimulationBar = function ($userId) {
        require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
        $app = fluentMail();
        $original = $app->make(Manager::class);
        $previousUser = get_current_user_id();
        FsmtpOverheadManagerSpy::$used = false;
        $app->instance(Manager::class, new FsmtpOverheadManagerSpy());

        try {
            wp_set_current_user($userId);
            $adminBar = new WP_Admin_Bar();
            ob_start();
            (new AdminMenuHandler($app))->addSimulationBar($adminBar);
            ob_end_clean();
            return [$adminBar->get_node('fluentsmtp_simulated') !== null, FsmtpOverheadManagerSpy::$used];
        } finally {
            $app->instance(Manager::class, $original);
            wp_set_current_user($previousUser);
        }
    };

    FsmtpTest::case('admin-bar simulation notice does not build the mailer Manager', function () use ($renderSimulationBar) {
        $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
        list($shown, $managerUsed) = $renderSimulationBar((int) $admins[0]);

        // The suite forces FLUENTMAIL_SIMULATE_EMAILS, so a manager always sees the notice.
        FsmtpTest::assertSame(true, $shown, 'simulation notice for a manager while simulation is on');
        FsmtpTest::assertSame(false, $managerUsed, 'Manager resolved by the admin-bar simulation check');
    });

    FsmtpTest::case('admin-bar simulation notice stays hidden from non-managers', function () use ($renderSimulationBar) {
        $existing = get_user_by('login', 'fsmtp_overhead_subscriber');
        $userId = $existing ? (int) $existing->ID : 0;
        $created = false;
        if (!$userId) {
            $userId = wp_insert_user([
                'user_login' => 'fsmtp_overhead_subscriber',
                'user_pass'  => wp_generate_password(24),
                'user_email' => 'fsmtp-overhead-subscriber@example.test',
                'role'       => 'subscriber',
            ]);
            $created = true;
        }

        try {
            list($shown) = $renderSimulationBar($userId);
            FsmtpTest::assertSame(false, $shown, 'simulation notice for a subscriber');
        } finally {
            if ($created) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                wp_delete_user($userId);
            }
        }
    });

    /**
     * Raw stored settings decide the notice the same way the Manager config did:
     * only 'yes' switches it on, and a missing or null value falls back to off.
     */
    FsmtpTest::case('simulation setting reads the same from stored settings as from the Manager config', function () {
        $manager = fluentMail(Manager::class);
        $configured = $manager->getConfig('misc.simulate_emails', 'no') === 'yes';
        $settings = fluentMailGetSettings();
        $stored = isset($settings['misc']['simulate_emails']) && $settings['misc']['simulate_emails'] === 'yes';
        FsmtpTest::assertSame($configured, $stored, 'simulate_emails from stored settings vs Manager config');
    });
};
