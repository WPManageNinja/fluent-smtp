<?php

namespace FluentMail\App\Models;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Models\Traits\SendTestEmailTrait;

class Settings
{
    use SendTestEmailTrait;

    protected $optionName = FLUENTMAIL . '-settings';

    public function get()
    {
        return fluentMailGetSettings();
    }

    public function getSettings()
    {
        return $this->get();
    }

    public function getNextConnectionId()
    {
        $settings = $this->getSettings();
        $connections = $this->getConnections($settings);
        $maxId = -1;
        foreach ($connections as $connection) {
            $connId = Arr::get($connection, 'provider_settings.connection_id');
            if (!is_null($connId)) {
                $maxId = max($maxId, (int)$connId);
            }
        }
        return $maxId + 1;
    }

    public function getConnectionByIntId($id)
    {
        $settings = $this->getSettings();
        $connections = $this->getConnections($settings);
        foreach ($connections as $key => $connection) {
            $connId = Arr::get($connection, 'provider_settings.connection_id');
            if (!is_null($connId) && (int)$connId === (int)$id) {
                return [$key, $connection];
            }
        }
        return [null, null];
    }

    public function assignConnectionIds()
    {
        $settings = $this->getSettings();
        if (empty($settings['connections'])) {
            return;
        }

        $modified = false;

        $maxId = -1;
        foreach ($settings['connections'] as $connection) {
            $connId = Arr::get($connection, 'provider_settings.connection_id');
            if (!is_null($connId)) {
                $maxId = max($maxId, (int)$connId);
            }
        }

        $nextId = $maxId + 1;

        foreach ($settings['connections'] as $key => $connection) {
            $connId = Arr::get($connection, 'provider_settings.connection_id');
            if (is_null($connId)) {
                $settings['connections'][$key]['provider_settings']['connection_id'] = $nextId;
                $nextId++;
                $modified = true;
            }
        }

        $defaultMd5 = Arr::get($settings, 'misc.default_connection');
        if ($defaultMd5 && isset($settings['connections'][$defaultMd5])) {
            $intId = Arr::get($settings['connections'][$defaultMd5], 'provider_settings.connection_id');
            if (!is_null($intId)) {
                $settings['misc']['default_connection'] = (int)$intId;
                $modified = true;
            }
        }

        $fallbackMd5 = Arr::get($settings, 'misc.fallback_connection');
        if ($fallbackMd5 && isset($settings['connections'][$fallbackMd5])) {
            $intId = Arr::get($settings['connections'][$fallbackMd5], 'provider_settings.connection_id');
            if (!is_null($intId)) {
                $settings['misc']['fallback_connection'] = (int)$intId;
                $modified = true;
            }
        }

        if ($modified) {
            fluentMailSetSettings($settings);
        }
    }

    public function store($inputs)
    {
        $settings = $this->getSettings();
        $mappings = $this->getMappings($settings);
        $connections = $this->getConnections($settings);
        $email = Arr::get($inputs, 'connection.sender_email');

        $key = $inputs['connection_key'];

        $existingConnectionId = null;
        if (isset($connections[$key])) {
            $existingConnectionId = Arr::get($connections[$key], 'provider_settings.connection_id');
            $mappings = array_filter($mappings, function ($mappingKey) use ($key) {
                return $mappingKey != $key;
            });
            unset($connections[$key]);
        }

        if (is_null($existingConnectionId)) {
            $existingConnectionId = $this->getNextConnectionId();
        }

        $inputs['connection']['connection_id'] = $existingConnectionId;

        $primaryEmails = [];
        foreach ($connections as $connection) {
            $primaryEmails[] = $connection['provider_settings']['sender_email'];
        }

        $uniqueKey = $this->generateUniqueKey($email);

        $extraMappings = $inputs['valid_senders'];

        foreach ($extraMappings as $emailIndex => $email) {
            if (in_array($email, $primaryEmails)) {
                unset($extraMappings[$emailIndex]);
            }
        }

        $extraMappings[] = $email;
        $extraMappings = array_unique($extraMappings);
        $extraMappings = array_fill_keys($extraMappings, $uniqueKey);

        $mappings = array_merge($mappings, $extraMappings);

        $providers = fluentMail(Manager::class)->getConfig('providers');

        $title = $providers[$inputs['connection']['provider']]['title'];

        $connections[$uniqueKey] = [
            'title'             => $title,
            'provider_settings' => $inputs['connection']
        ];

        $settings['mappings'] = $mappings;

        $settings['connections'] = $connections;

        if ($settings['mappings'] && $settings['connections']) {
            $validMappings = array_keys(Arr::get($settings, 'connections', []));

            $settings['mappings'] = array_filter($settings['mappings'], function ($key) use ($validMappings) {
                return in_array($key, $validMappings);
            });
        }

        $misc = $this->getMisc();

        if (!$misc) {
            $misc = [
                'log_emails'              => 'yes',
                'log_saved_interval_days' => '14',
                'disable_fluentcrm_logs'  => 'no',
                'default_connection'      => ''
            ];
        }

        $defaultConnectionId = Arr::get($misc, 'default_connection');
        $isDefaultMatched = false;
        if (empty($defaultConnectionId)) {
            $isDefaultMatched = true;
        } else {
            if (!is_null($existingConnectionId) && (string)$defaultConnectionId === (string)$existingConnectionId) {
                $isDefaultMatched = true;
            } elseif ((string)$defaultConnectionId === (string)$key) {
                $isDefaultMatched = true;
            }
        }

        if ($isDefaultMatched) {
            $misc['default_connection'] = $existingConnectionId;
            $settings['misc'] = $misc;
        }

        fluentMailSetSettings($settings);

        return $settings;
    }

    public function generateUniqueKey($email)
    {
        return md5($email);
    }

    public function saveGlobalSettings($data)
    {
        return fluentMailSetSettings($data);
    }

    public function delete($key)
    {
        $settings = $this->getSettings();

        $mappings = $settings['mappings'];
        $connections = $settings['connections'];

        $deletedConnection = isset($connections[$key]) ? $connections[$key] : null;
        $deletedIntId = $deletedConnection ? Arr::get($deletedConnection, 'provider_settings.connection_id') : null;

        unset($connections[$key]);

        foreach ($mappings as $mapKey => $mapValue) {
            if ($mapValue == $key) {
                unset($mappings[$mapKey]);
            }
        }

        $settings['mappings'] = $mappings;
        $settings['connections'] = $connections;

        $defaultConnectionId = Arr::get($settings, 'misc.default_connection');
        $isDefaultMatched = false;
        if (!is_null($defaultConnectionId) && $defaultConnectionId !== '') {
            if (!is_null($deletedIntId) && (string)$defaultConnectionId === (string)$deletedIntId) {
                $isDefaultMatched = true;
            } elseif ((string)$defaultConnectionId === (string)$key) {
                $isDefaultMatched = true;
            }
        }

        if ($isDefaultMatched) {
            if (count($connections)) {
                $firstConn = reset($connections);
                $newDefaultId = Arr::get($firstConn, 'provider_settings.connection_id');
                Arr::set($settings, 'misc.default_connection', !is_null($newDefaultId) ? (int)$newDefaultId : '');
            } else {
                Arr::set($settings, 'misc.default_connection', '');
            }
        }

        $fallbackConnectionId = Arr::get($settings, 'misc.fallback_connection');
        $isFallbackMatched = false;
        if (!is_null($fallbackConnectionId) && $fallbackConnectionId !== '') {
            if (!is_null($deletedIntId) && (string)$fallbackConnectionId === (string)$deletedIntId) {
                $isFallbackMatched = true;
            } elseif ((string)$fallbackConnectionId === (string)$key) {
                $isFallbackMatched = true;
            }
        }

        if ($isFallbackMatched) {
            Arr::set($settings, 'misc.fallback_connection', '');
        }

        fluentMailSetSettings($settings);

        return $settings;
    }

    public function getDefaults()
    {
        $url = str_replace(
            ['http://', 'http://www.', 'www.'],
            '',
            get_bloginfo('wpurl')
        );

        return [
            'sender_name'  => $url,
            'sender_email' => get_option('admin_email')
        ];
    }

    public function getVerifiedEmails()
    {
        $optionName = FLUENTMAIL . '-ses-verified-emails';

        return get_option($optionName, []);
    }

    public function saveVerifiedEmails($verifiedEmails)
    {
        $optionName = FLUENTMAIL . '-ses-verified-emails';
        $emails = get_option($optionName, []);
        update_option($optionName, array_unique(array_merge(
            $emails, $verifiedEmails
        )));
    }

    public function getConnections($settings = null)
    {
        $settings = $settings ?: $this->getSettings();

        return Arr::get($settings, 'connections', []);
    }

    public function getMappings($settings = null)
    {
        $settings = $settings ?: $this->getSettings();

        return Arr::get($settings, 'mappings', []);
    }

    public function getMisc($settings = null)
    {
        $settings = $settings ?: $this->getSettings();

        return Arr::get($settings, 'misc', []);
    }

    public function getConnection($email)
    {
        $settings = $this->getSettings();
        $mappings = $this->getMappings($settings);
        $connections = $this->getConnections($settings);

        if (isset($mappings[$email])) {
            if (isset($connections[$mappings[$email]])) {
                return $connections[$mappings[$email]];
            }
        }

        return [];
    }

    public function updateMiscSettings($misc)
    {
        $settings = $this->get();
        $settings['misc'] = $misc;
        $this->saveGlobalSettings($settings);
    }

    public function updateConnection($fromEmail, $connection)
    {
        $key = $this->generateUniqueKey($fromEmail);
        $settings = $this->getSettings();
        $settings['connections'][$key]['provider_settings'] = $connection;
        $this->saveGlobalSettings($settings);
    }

    public function notificationSettings()
    {
        $defaults = [
            'enabled'        => 'no',
            'notify_email'   => '{site_admin}',
            'notify_days'    => ['Mon'],
            'active_channel' => [],
            'telegram'       => [
                'status' => 'no',
                'token'  => ''
            ],
            'slack'          => [
                'status'      => 'no',
                'token'       => '',
                'webhook_url' => ''
            ],
            'discord'        => [
                'status'       => 'no',
                'channel_name' => '',
                'webhook_url'  => ''
            ],
        ];

        $settings = get_option('_fluent_smtp_notify_settings', []);

        $settings = wp_parse_args($settings, $defaults);

        if (!is_array($settings['active_channel'])) {
            $settings['active_channel'] = array_filter([$settings['active_channel']]);
        }

        return $settings;
    }

    public function getAvailableNotificationChannels()
    {
        $manager = new \FluentMail\App\Services\Notification\Manager();
        return $manager->getAllChannels();
    }
}
