<?php

namespace FluentMail\App\Services\Mailer\Providers;

use InvalidArgumentException;
use FluentMail\App\Models\Settings;
use FluentMail\App\Services\Mailer\BaseHandler;
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

    /**
     * @param string $provider a provider key from app/Bindings.php
     * @return BaseHandler
     * @throws InvalidArgumentException for anything that is not one
     */
    public function make($provider)
    {
        /*
         * The key arrives in the admin's own POST in several controllers, and the
         * container builds any instantiable class it is handed by name. Only the
         * aliases registered in app/Bindings.php are mail handlers, so anything
         * else - a class name, a service alias such as 'view' - is refused, and
         * a class name is refused before the container is asked to construct it.
         */
        if (!is_string($provider) || !$this->app->isAlias($provider)) {
            throw new InvalidArgumentException(esc_html__('Unknown mail provider.', 'fluent-smtp'));
        }

        $handler = $this->app->make($provider);

        if (!$handler instanceof BaseHandler) {
            throw new InvalidArgumentException(esc_html__('Unknown mail provider.', 'fluent-smtp'));
        }

        return $handler;
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
