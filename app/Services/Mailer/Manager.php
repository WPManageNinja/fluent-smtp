<?php

namespace FluentMail\App\Services\Mailer;

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

    protected static $providerNamespace = 'FluentMail\App\Services\Mailer\Providers';

    public function __construct(Application $app = null)
    {
        $this->app = $app ?: fluentMail();

        $this->initialize();
    }

    protected function initialize()
    {
        $this->loadConfigAndSettings();

        $this->app->addCustomFilter('active_driver', [$this, 'activeDriver']);

        $this->app->addCustomFilter('is_plugin_inactive', [$this, 'isPluginInactive']);

        $this->app->addAction('fluent_mail_delete_email_logs', [$this, 'maybeDeleteLogs']);
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
                    Arr::get($databaseSettings, $optionKey, []),
                );
                
                Arr::set(static::$config, $optionKey, $options);

                // $this->validate(static::$config, $key);

                static::$config['providers'][$key]['is_ready'] = true;

            } catch (ValidationException $e) {
                static::$config['providers'][$key]['is_ready'] = !$e->errors();
                continue;
            }
        }
    }

    public function getMailerConfigAndSettings()
    {
        return static::$config;
    }

    public function getConfig($key = null)
    {
        return $key ? Arr::get(static::$config, $key) : static::$config;
    }

    public function getSettings($key = null)
    {
        return $key ? Arr::get(static::$settings, $key) : static::$settings;
    }

    public function getWPConfig($key = null)
    {
        return $key ? Arr::get(static::$wpConfigSettings, $key) : static::$wpConfigSettings;
    }

    public function getOriginalConfig($key)
    {
        $config = require(__DIR__ . '/Providers/config.php');

        return $config['providers'][$key];
    }

    public function activeDriver($phpMailer)
    {
        return fluentMailgetConnection($phpMailer->From);
        // return fluentMail(Factory::class)->get($phpMailer->From);
    }

    public function isPluginInactive()
    {
        return $this->getSettings('misc.is_inactive') == 'yes';
    }

    public function maybeDeleteLogs()
    {
        $logSettings = $this->getSettings('misc');

        if (isset($logSettings['on']) && $logSettings['on'] == 'yes') {

            if ($logSettings['interval'] != 'never') {
                $this->app
                    ->make(\FluentMail\App\Models\Logger::class)
                    ->deleteLogsOlderThan($logSettings['log_saved_interval_days']);
            }
        }
    }
}
