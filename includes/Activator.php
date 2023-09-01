<?php

namespace FluentMail\Includes;

class Activator
{
    public static function handle($network_wide = false)
    {
        require_once(FLUENTMAIL_PLUGIN_PATH . 'database/FluentMailDBMigrator.php');

        $emailReportHookName = 'fluentmail_do_daily_scheduled_tasks';
        if (!wp_next_scheduled($emailReportHookName)) {
            wp_schedule_event(time(), 'daily', $emailReportHookName);
        }
        
        add_filter('pre_update_option_active_plugins', function ($plugins) {
            $index = array_search('fluent-smtp/fluent-smtp.php', $plugins);
            if ($index !== false) {
                if ($index === 0) {
                    return $plugins;
                }
                unset($plugins[$index]);
                array_unshift($plugins, 'fluent-smtp/fluent-smtp.php');
            }
            return $plugins;
        });
    }
}
