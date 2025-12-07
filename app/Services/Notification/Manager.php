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
        static::$config['active_channel'] = Arr::get($databaseSettings, 'active_channel', '');
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

    public function getActiveChannel()
    {
        $activeChannelKey = Arr::get(static::$config, 'active_channel', '');

        if (!$activeChannelKey) {
            return null;
        }

        $channel = $this->getChannel($activeChannelKey);

        if (!$channel) {
            return null;
        }

        $channelSettings = Arr::get(static::$settings, $activeChannelKey, []);

        if (empty($channelSettings['status']) || $channelSettings['status'] != 'yes') {
            return null;
        }

        $channel['driver'] = $activeChannelKey;
        $channel['settings'] = $channelSettings;

        return $channel;
    }

    public function getActiveChannelSettings()
    {
        return $this->getActiveChannel();
    }

    public function isChannelActive($key)
    {
        $activeChannel = Arr::get(static::$config, 'active_channel', '');
        return $activeChannel === $key;
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

    public function disableOtherChannels($activeChannelKey, &$settings)
    {
        $allChannelKeys = $this->getAllChannelKeys();

        foreach ($allChannelKeys as $channelKey) {
            if ($channelKey !== $activeChannelKey && isset($settings[$channelKey]) && is_array($settings[$channelKey])) {
                $settings[$channelKey]['status'] = 'no';
            }
        }
    }
}
