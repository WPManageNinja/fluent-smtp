<?php

namespace FluentMail\App\Models;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Models\Traits\SendTestEmailTrait;
use FluentMail\Includes\Support\ValidationException;

class Settings
{
    use SendTestEmailTrait;

    protected $optionName = FLUENTMAIL . '-settings';

    public function get()
    {
        return get_option($this->optionName, []);
    }

    public function getSettings()
    {
        return $this->get();
    }

    public function store($inputs)
    {
        $settings = $this->getSettings();   
        $mappings = $this->getMappings($settings);
        $connections = $this->getConnections($settings);
        $email = Arr::get($inputs, 'connection.sender_email');

        $key = $inputs['connection_key'];


        if (isset($connections[$key])) {
            $mappings = array_filter($mappings, function($mappingKey) use ($key) {
                return $mappingKey != $key;
            });
            unset($connections[$key]);
        }

        $primaryEmails = [];
        foreach ($connections as $connection) {
            $primaryEmails[] = $connection['provider_settings']['sender_email'];
        }

        $uniqueKey = $this->generateUniqueKey($email);

        $extraMappings = $inputs['valid_senders'];

        foreach ($extraMappings as $emailIndex => $email) {
            if(in_array($email, $primaryEmails)) {
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
            'title' => $title,
            'provider_settings' => $inputs['connection']
        ];

        $settings['mappings'] = $mappings;

        $settings['connections'] = $connections;


        if($settings['mappings'] && $settings['connections']) {
            $validMappings = array_keys(Arr::get($settings, 'connections', []));

            $settings['mappings'] = array_filter($settings['mappings'], function ($key) use ($validMappings) {
                return in_array($key, $validMappings);
            });
        }

        $misc = $this->getMisc();

        if(!$misc) {
            $misc = [
                'log_emails' => 'yes',
                'log_saved_interval_days' => '14',
                'disable_fluentcrm_logs' => 'no',
                'default_connection' => ''
            ];
        }

        if(empty($misc['default_connection']) || $misc['default_connection'] == $key) {
            $misc['default_connection'] = $uniqueKey;
            $settings['misc'] = $misc;
        }

        update_option($this->optionName, $settings);

        return $settings;
    }

    public function generateUniqueKey($email)
    {
        return md5($email);
    }

    public function saveGlobalSettings($data)
    {
        return update_option($this->optionName, $data);
    }

    public function delete($key)
    {
        $settings = $this->getSettings();


        $mappings = $settings['mappings'];
        $connections = $settings['connections'];

        unset($connections[$key]);

        foreach ($mappings as $mapKey => $mapValue) {
            if($mapValue == $key) {
                unset($mappings[$mapKey]);
            }
        }

        $settings['mappings'] = $mappings;
        $settings['connections'] = $connections;

        if (Arr::get($settings, 'misc.default_connection') == $key) {
            $default = Arr::get($settings, 'mappings', []);
            $default = reset($default);
            Arr::set($settings, 'misc.default_connection', $default ?: '');
        }

        if (Arr::get($settings, 'misc.fallback_connection') == $key) {
            Arr::set($settings, 'misc.fallback_connection', '');
        }

        update_option($this->optionName, $settings);

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
            'sender_name' => $url,
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
            'enabled' => 'no',
            'notify_email' => '{site_admin}',
            'notify_days' => ['Mon']
        ];

        $settings = get_option('_fluent_smtp_notify_settings', []);

        return wp_parse_args($settings, $defaults);
    }
}
