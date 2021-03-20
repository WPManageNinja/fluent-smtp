<?php

use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\Providers\Factory;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailService;

if (!function_exists('fluentMail')) {
    function fluentMail($module = null)
    {
        return FluentMail\App\App::getInstance($module);
    }
}

if (!function_exists('fluentMailMix')) {
    function fluentMailMix($path, $manifestDirectory = '')
    {
        return fluentMail('url.assets') . ltrim($path, '/');
    }
}

if (!function_exists('fluentMailAssetUrl')) {
    function fluentMailAssetUrl($path = null)
    {
        $assetUrl = fluentMail('url.assets');
        return $path ? ($assetUrl . $path) : $assetUrl;
    }
}

if (!function_exists('dd')) {
    function dd()
    {
        foreach (func_get_args() as $arg) {
            echo "<pre>";
            print_r($arg);
            echo "</pre>";
        }
        die;
    }
}

if (!function_exists('ddd')) {
    function ddd($data)
    {
        foreach (func_get_args() as $arg) {
            echo "<pre>";
            print_r($arg);
            echo "</pre>";
        }
    }
}

if (!function_exists('fluentMailWpParseArgs')) {
    function fluentMailWpParseArgs($args, $defaults = [])
    {
        $newArgs = (array)$defaults;

        foreach ($args as $key => $value) {
            if (is_array($value) && isset($newArgs[$key])) {
                $newArgs[$key] = fluentMailWpParseArgs($value, $newArgs[$key]);
            } else {
                $newArgs[$key] = $value;
            }
        }

        return $newArgs;
    }
}

if (!function_exists('fluentMailIsListedSenderEmail')) {
    function fluentMailIsListedSenderEmail($email)
    {
        static $settings;

        if (!$settings) {
            $settings = get_option('fluentmail-settings');
        }

        if (!$settings) {
            return false;
        }
        return !empty($settings['mappings'][$email]);
    }
}

if (!function_exists('fluentMailDefaultConnection')) {
    function fluentMailDefaultConnection()
    {
        static $defaultConnection;

        if ($defaultConnection) {
            return $defaultConnection;
        }

        $settings = get_option('fluentmail-settings');

        if (!$settings) {
            return [];
        }

        if (
            isset($settings['misc']['default_connection']) &&
            isset($settings['connections'][$settings['misc']['default_connection']])
        ) {
            $default = $settings['misc']['default_connection'];
            $defaultConnection = $settings['connections'][$default]['provider_settings'];
        } else if (count($settings['connections'])) {
            $connection = reset($settings['connections']);
            $defaultConnection = $connection['provider_settings'];
        } else {
            $defaultConnection = [];
        }

        return $defaultConnection;

    }
}

if (!function_exists('fluentMailgetConnection')) {
    function fluentMailgetConnection($email)
    {
        $factory = fluentMail(Factory::class);
        if (!($connection = $factory->get($email))) {
            $connection = fluentMailDefaultConnection();
        }

        return $connection;
    }
}

if (!function_exists('fluentMailGetProvider')) {
    function fluentMailGetProvider($fromEmail)
    {
        static $providers = [];

        if (isset($providers[$fromEmail])) {
            return $providers[$fromEmail];
        }

        $manager = fluentMail(Manager::class);

        $mappings = $manager->getSettings('mappings');

        $connection = false;

        if (isset($mappings[$fromEmail])) {
            $connectionId = $mappings[$fromEmail];
            $connections = $manager->getSettings('connections');
            if(isset($connections[$connectionId])) {
                $connection = $connections[$connectionId]['provider_settings'];
            }
        }

        if(!$connection) {
            $connection = fluentMailDefaultConnection();
            if($connection) {
                $connection['force_from_email_id'] = $connection['sender_email'];
            }
        }

        if ($connection) {
            $factory = fluentMail(Factory::class);
            $driver = $factory->make($connection['provider']);
            $driver->setSettings($connection);
            $providers[$fromEmail] = $driver;
        } else {
            $providers[$fromEmail] = false;
        }

        return $providers[$fromEmail];
    }
}

if (!function_exists('fluentMailSesConnection')) {
    function fluentMailSesConnection($connection)
    {
        static $drivers = [];

        if (isset($drivers[$connection['sender_email']])) {
            return $drivers[$connection['sender_email']];
        }

        $region = 'email.' . $connection['region'] . '.amazonaws.com';

        $ses = new SimpleEmailService(
            $connection['access_key'],
            $connection['secret_key'],
            $region,
            false
        );

        $drivers[$connection['sender_email']] = $ses;

        return $drivers[$connection['sender_email']];
    }
}