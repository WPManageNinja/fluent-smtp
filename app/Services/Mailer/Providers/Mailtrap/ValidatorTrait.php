<?php

namespace FluentMail\App\Services\Mailer\Providers\Mailtrap;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;

trait ValidatorTrait
{
    use BaseValidatorTrait;

    public function validateProviderInformation($connection)
    {
        $errors = [];

        $keyStoreType = Arr::get($connection, 'key_store');

        if ($keyStoreType == 'db') {
            if (!Arr::get($connection, 'api_key')) {
                $errors['api_key']['required'] = __('API token is required.', 'fluent-smtp');
            }
        } elseif ($keyStoreType == 'wp_config') {
            if (!defined('FLUENTMAIL_MAILTRAP_API_KEY') || !FLUENTMAIL_MAILTRAP_API_KEY) {
                $errors['api_key']['required'] = __('Please define FLUENTMAIL_MAILTRAP_API_KEY in wp-config.php file.', 'fluent-smtp');
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

}
