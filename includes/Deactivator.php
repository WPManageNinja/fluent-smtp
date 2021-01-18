<?php

namespace FluentMail\Includes;

class Deactivator
{
    public static function handle($network_wide = false)
    {
        wp_clear_scheduled_hook('fluentmail_do_daily_scheduled_tasks');
    }
}
