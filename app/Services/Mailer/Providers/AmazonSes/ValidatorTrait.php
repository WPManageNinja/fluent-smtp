<?php

namespace FluentMail\App\Services\Mailer\Providers\AmazonSes;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;

trait ValidatorTrait
{
    use BaseValidatorTrait;

    public function validateProviderInformation($connection)
    {
        $errors = [];

        $keyStoreType = $connection['key_store'];

        if($keyStoreType == 'db') {
            if (! Arr::get($connection, 'access_key')) {
                $errors['access_key']['required'] = __('Access key is required.', 'fluent-smtp');
            }

            if (! Arr::get($connection, 'secret_key')) {
                $errors['secret_key']['required'] = __('Secret key is required.', 'fluent-smtp');
            }
        } else if($keyStoreType == 'wp_config') {
            if(!defined('FLUENTMAIL_AWS_ACCESS_KEY_ID') || !FLUENTMAIL_AWS_ACCESS_KEY_ID) {
                $errors['access_key']['required'] = __('Please define FLUENTMAIL_AWS_ACCESS_KEY_ID in wp-config.php file.', 'fluent-smtp');
            }

            if(!defined('FLUENTMAIL_AWS_SECRET_ACCESS_KEY') || !FLUENTMAIL_AWS_SECRET_ACCESS_KEY) {
                $errors['secret_key']['required'] = __('Please define FLUENTMAIL_AWS_SECRET_ACCESS_KEY in wp-config.php file.', 'fluent-smtp');
            }
        }


        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

    public function checkConnection($connection)
    {
        $connection = $this->filterConnectionVars($connection);
        $region = 'email.' . $connection['region'] . '.amazonaws.com';

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        $ses = new SimpleEmailService(
            $connection['access_key'],
            $connection['secret_key'],
            $region,
            true
        );

        try {
            $ses->listVerifiedEmailAddresses();
        } catch (\Exception $e) {
            $this->throwValidationException(['api_error' => $e->getMessage()]);
        }

        return true;
    }
}
