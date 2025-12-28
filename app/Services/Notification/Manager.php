<?php

namespace FluentMail\App\Services\Notification;

use FluentMail\App\Models\Settings;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;

class Manager
{
    protected $app = null;

    protected static $config = [];

    protected static $settings = [];

    public function __construct(?Application $app = null)
    {
        $this->app = $app ?: fluentMail();

        $this->initialize();
    }

    protected function initialize()
    {
        $this->loadConfigAndSettings();
    }

    protected function loadConfigAndSettings()
    {
        static::$config = require(__DIR__ . '/config.php');

        static::$settings = (new Settings())->notificationSettings();

        $this->mergeConfigAndSettings();
    }

    protected function mergeConfigAndSettings()
    {
        $databaseSettings = $this->getSettings();

        // Merge database settings with config
        foreach (static::$config['channels'] as $key => $channel) {
            $channelSettings = Arr::get($databaseSettings, $key, []);

            if ($channelSettings) {
                static::$config['channels'][$key]['settings'] = $channelSettings;
            }
        }

        // Set active channel from database
        static::$config['active_channel'] = Arr::get($databaseSettings, 'active_channel', []);
    }

    public function getConfig($key = null, $default = null)
    {
        return $key ? Arr::get(static::$config, $key, $default) : static::$config;
    }

    public function getSettings($key = null, $default = null)
    {
        return $key ? Arr::get(static::$settings, $key, $default) : static::$settings;
    }

    public function getAllChannels()
    {
        return static::$config['channels'] ?? [];
    }

    public function getChannel($key)
    {
        return Arr::get(static::$config['channels'], $key, null);
    }

    public function getActiveChannels()
    {
        static $activeChannels;
        if (isset($activeChannels)) {
            return $activeChannels;
        }

        $activeChannelKeys = Arr::get(static::$config, 'active_channel', []);
        if (!$activeChannelKeys || !is_array($activeChannelKeys)) {
            return [];
        }

        $activeChannels = [];

        foreach ($activeChannelKeys as $activeChannelKey) {
            $channel = $this->getChannel($activeChannelKey);

            if (!$channel) {
                continue;
            }

            $channelSettings = Arr::get(static::$settings, $activeChannelKey, []);

            if (empty($channelSettings['status']) || $channelSettings['status'] != 'yes') {
                continue;
            }

            $channel['driver'] = $activeChannelKey;
            $channel['settings'] = $channelSettings;

            $activeChannels[] = $channel;
        }

        return $activeChannels;
    }

    public function getChannelStatus($key)
    {
        $channelSettings = Arr::get(static::$settings, $key, []);
        return Arr::get($channelSettings, 'status', 'no');
    }

    public function getAllChannelKeys()
    {
        return array_keys(static::$config['channels'] ?? []);
    }

}
