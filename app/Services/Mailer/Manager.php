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

    public function __construct(Application $app = null)
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
            Arr::set(static::$config, "misc", array_merge(
                static::$config['misc'], $databaseSettings['misc']
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
