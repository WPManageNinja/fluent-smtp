<?php

namespace FluentMail\App\Hooks\Handlers;

class InitializeSiteHandler
{
    public function addHandler()
    {
        add_action('wp_initialize_site', array($this, 'handle'));
    }

    public static function handle( $new_site )
    {
        require_once(FLUENTMAIL_PLUGIN_PATH . 'database/migrations/EmailLogs.php');
        
        $blog_id = $new_site->blog_id;
        switch_to_blog((int)$blog_id);
        \FluentMailMigrations\EmailLogs::migrate();
        restore_current_blog();
    }
}
