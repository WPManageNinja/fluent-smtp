<?php

namespace FluentMail\App\Services\Mailer\Providers\Sweego;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;

trait ValidatorTrait
{
    use BaseValidatorTrait;

    public function validateProviderInformation($connection)
    {
        $errors = [];

        $keyStoreType = Arr::get($connection, 'key_store', 'db');

        if($keyStoreType == 'db') {
            if (!Arr::get($connection, 'api_key')) {
                $errors['api_key']['required'] = __('API key is required.', 'fluent-smtp');
            }
        } else if($keyStoreType == 'wp_config') {
            if(!defined('SWEEGO_API_KEY') || !SWEEGO_API_KEY) {
                $errors['api_key']['required'] = __('Please define SWEEGO_API_KEY in wp-config.php file.', 'fluent-smtp');
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

    public function checkConnection($connection)
    {
        return true;
    }

    public function throwValidationException($errors) // Changed from private to public
    {
        throw new \Exception(json_encode($errors));
    }
}
