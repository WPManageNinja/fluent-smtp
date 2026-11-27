<?php

namespace FluentMail\App\Services\Mailer\Providers\Postal;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;

trait ValidatorTrait
{
    use BaseValidatorTrait;

    public function validateProviderInformation($connection)
    {
        $errors = [];

        $serverUrl = trim((string)Arr::get($connection, 'server_url', ''));

        if ($serverUrl === '') {
            $errors['server_url']['required'] = __('Postal server URL is required.', 'fluent-smtp');
        } else {
            $validated = filter_var($serverUrl, FILTER_VALIDATE_URL);
            $scheme = $validated ? strtolower((string)parse_url($validated, PHP_URL_SCHEME)) : '';

            if (!$validated || !in_array($scheme, ['http', 'https'], true)) {
                $errors['server_url']['invalid'] = __('Enter a valid Postal server URL (http or https).', 'fluent-smtp');
            }
        }

        $keyStoreType = Arr::get($connection, 'key_store');

        if ($keyStoreType == 'db') {
            if (!Arr::get($connection, 'api_key')) {
                $errors['api_key']['required'] = __('Api key is required.', 'fluent-smtp');
            }
        } else if ($keyStoreType == 'wp_config') {
            if (!defined('FLUENTMAIL_POSTAL_API_KEY') || !FLUENTMAIL_POSTAL_API_KEY) {
                $errors['api_key']['required'] = __('Please define FLUENTMAIL_POSTAL_API_KEY in wp-config.php file.', 'fluent-smtp');
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
}
