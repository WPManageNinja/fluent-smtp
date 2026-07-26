<?php

namespace FluentMail\App\Services\Mailer\Providers\Emailit;

use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;
use FluentMail\Includes\Support\Arr;

trait ValidatorTrait
{
    use BaseValidatorTrait;

    /**
     * Validate Emailit provider settings.
     *
     * @param array $connection Connection settings.
     *
     * @return void
     */
    public function validateProviderInformation($connection)
    {
        $errors = [];
        $keyStore = Arr::get($connection, 'key_store', 'db');

        if ($keyStore === 'db') {
            $apiKey = Arr::get($connection, 'api_key', '');

            if (!is_string($apiKey) || trim($apiKey) === '') {
                $errors['api_key']['required'] = __(
                    'API key is required.',
                    'fluent-smtp'
                );
            }
        } elseif ($keyStore === 'wp_config') {
            if (
                !defined('FLUENTMAIL_EMAILIT_API_KEY') ||
                !FLUENTMAIL_EMAILIT_API_KEY
            ) {
                $errors['api_key']['required'] = __(
                    'Please define FLUENTMAIL_EMAILIT_API_KEY in wp-config.php file.',
                    'fluent-smtp'
                );
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

    /**
     * Skip remote credential verification while saving the connection.
     *
     * @param array $connection Connection settings.
     *
     * @return bool
     */
    public function checkConnection($connection)
    {
        return true;
    }
}
