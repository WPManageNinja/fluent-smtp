<?php

spl_autoload_register(function ($class) {

    $namespace = 'Viocon';

    if (substr($class, 0, strlen($namespace)) !== $namespace) {
        return;
    }

    $classPath = str_replace(
        array('\\', $namespace, strtolower($namespace)),
        array('/', 'src/Viocon', ''),
        $class
    );

    $basePath = plugin_dir_path(__FILE__);

    $file = $basePath.trim($classPath, '/').'.php';

    if (is_readable($file)) {
        include $file;
    }
});