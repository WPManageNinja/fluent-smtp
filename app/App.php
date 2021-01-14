<?php

namespace FluentMail\App;

use FluentMail\Includes\Core\Application;

class App
{
    public static function getInstance($module = null)
    {
        $app = Application::getInstance();

        if ($module) {
            return $app[$module];
        }

        return $app;
    }

    public static function __callStatic($method, $params)
    {
        return static::getInstance($method);
    }
}
