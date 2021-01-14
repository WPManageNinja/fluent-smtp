<?php

namespace FluentMail\Includes;

class Activator
{
    public static function handle($network_wide = false)
    {
        require_once(FLUENTMAIL_PLUGIN_PATH . 'database/FluentMailDBMigrator.php');
    }
}
