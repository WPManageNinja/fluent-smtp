<?php

!defined('WPINC') && die;

define('FLUENTMAIL', 'fluentmail');
define('FLUENTMAIL_PLUGIN_VERSION', '2.2.95');
define('FLUENTMAIL_UPLOAD_DIR', '/fluentmail');
define('FLUENT_MAIL_DB_PREFIX', 'fsmpt_');
define('FLUENTMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLUENTMAIL_PLUGIN_PATH', plugin_dir_path(__FILE__));


require __DIR__ . '/vendor/autoload.php';
