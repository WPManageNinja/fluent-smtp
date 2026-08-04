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
        if ($conn = $this->settings->getConnection($email)) {
            $settings = array_merge($conn['provider_settings'], [
                'title' => isset($conn['title']) ? $conn['title'] : ''
            ]);

            return $this->make(
                $conn['provider_settings']['provider']
            )->setSettings($settings);
        }

        /*
         * The two shapes differ. A stored connection is the wrapper
         * {title, provider_settings}, but fluentMailDefaultConnection()
         * returns the provider_settings payload itself. Unwrapping the
         * default a second time - as this used to - reads a key that is not
         * there and hands null to array_merge(), which is fatal. So the
         * default is used as-is.
         */
        $default = $this->getDefaultProvider();

        if ($default && !empty($default['provider'])) {
            return $this->make($default['provider'])->setSettings($default);
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
