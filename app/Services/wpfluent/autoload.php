<?php

// Autoload Service Container.

spl_autoload_register(function ($class) {

    $namespace = 'FluentSmtpDb';

    if (!preg_match("/\b{$namespace}\b/", $class)) {
        return;
    }

    $className = str_replace(
        array('\\', $namespace, strtolower($namespace)),
        array('/', 'src', ''),
        $class
    );

    $basePath = plugin_dir_path(__FILE__);

    $file = $basePath.trim($className, '/').'.php';

    if (is_readable($file)) {
        include $file;
    }
});
