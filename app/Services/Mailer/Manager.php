<?php

namespace FluentMail\App\Services\Mailer;

use FluentMail\App\Models\Logger;
use FluentMail\App\Models\Settings;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\Includes\Support\ValidationException;
use FluentMail\App\Services\Mailer\Providers\Factory;

class Manager
{
    protected $app = null;

    protected static $config = [];

    protected static $settings = [];
    
    protected static $resolved = [];
    
    protected static $wpConfigSettings = [];

    public function __construct(?Application $app = null)
    {
        $this->app = $app ?: fluentMail();

        $this->initialize();
    }

    protected function initialize()
    {
        $this->loadConfigAndSettings();

        $this->app->addCustomFilter('active_driver', [$this, 'activeDriver']);
    }

    protected function loadConfigAndSettings()
    {
        static::$config = require(__DIR__ . '/Providers/config.php');

        static::$settings = (new Settings)->getSettings();

        $this->mergeConfigAndSettings();
    }

    protected function mergeConfigAndSettings()
    {
        $databaseSettings = $this->getSettings();

        Arr::set(static::$config, 'mappings', Arr::get($databaseSettings, 'mappings'));
        Arr::set(static::$config, 'connections', Arr::get($databaseSettings, 'connections'));

        if (isset($databaseSettings['misc'])) {
            /*
             * Nulls are dropped rather than merged. A key stored as null - which is how
             * an unset fallback connection was written for a while - would otherwise
             * replace its default and reach the app as null, where a switch or a select
             * bound to it has no value it can render.
             */
            $storedMisc = array_filter((array) $databaseSettings['misc'], function ($value) {
                return !is_null($value);
            });

            Arr::set(static::$config, "misc", array_merge(
                static::$config['misc'], $storedMisc
            ));
        }

        foreach (static::$config['providers'] as $key => $provider) {
            try {
                $optionKey = "providers.{$key}.options";

                $options = array_merge(
                    $provider['options'],
                    Arr::get($databaseSettings, $optionKey, [])
                );
                
                Arr::set(static::$config, $optionKey, $options);

            } catch (ValidationException $e) {
                continue;
            }
        }
    }

    public function getMailerConfigAndSettings()
    {
        return static::$config;
    }

    public function getConfig($key = null, $default = null)
    {
        return $key ? Arr::get(static::$config, $key, $default) : static::$config;
    }

    public function getSettings($key = null, $default = null)
    {
        return $key ? Arr::get(static::$settings, $key, $default) : static::$settings;
    }

    public function getWPConfig($key = null, $default = null)
    {
        return $key ? Arr::get(
            static::$wpConfigSettings, $key, $default
        ) : static::$wpConfigSettings;
    }

    public function activeDriver($phpMailer)
    {
        return fluentMailgetConnection($phpMailer->From);
    }
}
