<?php

namespace FluentMail\App\Services\Mailer\Providers;

use InvalidArgumentException;
use FluentMail\App\Models\Settings;
use FluentMail\Includes\Core\Application;

class Factory
{
    protected $app = null;

    protected $settings = null;

    public function __construct(Application $app, Settings $settings)
    {
        $this->app = $app;
        
        $this->settings = $settings;
    }

    public function make($provider)
    {
        return $this->app->make($provider);
    }

    public function get($email)
    {
        if (!($conn = $this->settings->getConnection($email))) {
            $conn = $this->getDefaultProvider();
        }
        
        if ($conn) {
            $settings = array_merge($conn['provider_settings'], [
                'title' => $conn['title']
            ]);
            
            return $this->make(
                $conn['provider_settings']['provider']
            )->setSettings($settings);
        }
        

        throw new InvalidArgumentException(
            esc_html__('There is no matching provider found by email: ', 'fluent-smtp') . $email // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        );
    }

    public function getDefaultProvider()
    {
        return fluentMailDefaultConnection();
    }
}
