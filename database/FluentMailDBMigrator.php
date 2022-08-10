<?php

require_once(ABSPATH.'wp-admin/includes/upgrade.php');
require_once(FLUENTMAIL_PLUGIN_PATH.'database/migrations/EmailLogs.php');

class FluentMailDBMigrator
{
    public static function run($network_wide = false)
    {
        global $wpdb;

        if ($network_wide ) {
            if (function_exists('get_sites') && function_exists('get_current_network_id')) {
                $site_ids = get_sites(['fields' => 'ids', 'network_id' => get_current_network_id(), 'number' => get_blog_count()]);
            } else {
                $site_ids = $wpdb->get_col(
                    "SELECT blog_id FROM $wpdb->blogs WHERE site_id = $wpdb->siteid;"
                );
            }

            // Install the plugin for all these sites.
            foreach ($site_ids as $site_id) {
                switch_to_blog($site_id);
                self::migrate();
                restore_current_blog();
            }
        }  else {
            self::migrate();
        }
    }

    public static function migrate()
    {
        \FluentMailMigrations\EmailLogs::migrate();
    }
}

if (!isset($network_wide)) {
    $network_wide = false;
}

FluentMailDBMigrator::run($network_wide);
