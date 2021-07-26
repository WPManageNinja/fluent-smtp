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
    }
}
