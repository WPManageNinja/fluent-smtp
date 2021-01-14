<?php
/*
Plugin Name:  Fluent SMTP
Plugin URI:   https://github.com/WPManageNinja/fluent-smtp
Description:  The Ultimate SMTP Connection Plugin for WordPress.
Version:      1.0.0
Author:       WPManageNinja Team
Author URI:   https://wpmanageninja.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  fluent-smtp
Domain Path:  /language
*/

define( 'FLUENTMAIL_PLUGIN_FILE', __FILE__ );

require_once(plugin_dir_path(__FILE__).'boot.php');

register_activation_hook(
    __FILE__, array('FluentMail\Includes\Activator', 'handle')
);

register_deactivation_hook(
    __FILE__, array('FluentMail\Includes\Deactivator', 'handle')
);

call_user_func(function() {
    $application = new FluentMail\Includes\Core\Application;
    add_action('plugins_loaded', function() use ($application) {
        do_action('fluentMail_loaded', $application);
    });
});
