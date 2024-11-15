<?php

!defined('WPINC') && die;

define('FLUENTMAIL', 'fluentmail');
define('FLUENTMAIL_PLUGIN_VERSION', '2.2.80');
define('FLUENTMAIL_UPLOAD_DIR', '/fluentmail');
define('FLUENT_MAIL_DB_PREFIX', 'fsmpt_');
define('FLUENTMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLUENTMAIL_PLUGIN_PATH', plugin_dir_path( __FILE__ ));

spl_autoload_register(function($class) {
    $match = 'FluentMail';

    if (!preg_match("/\b{$match}\b/", $class)) {
        return;
    }

    $path = plugin_dir_path(__FILE__);
    
    $file = str_replace(
        ['FluentMail', '\\', '/App/', '/Includes/'],
        ['', DIRECTORY_SEPARATOR, 'app/', 'includes/'],
        $class
    );

    require(trailingslashit($path) . trim($file, '/') . '.php');
});

