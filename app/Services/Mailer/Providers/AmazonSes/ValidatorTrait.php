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

        if (! Arr::get($connection, 'access_key')) {
            $errors['access_key']['required'] = 'Access key is required.';
        }

        if (! Arr::get($connection, 'secret_key')) {
            $errors['secret_key']['required'] = 'Secret key is required.';
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

    public function checkConnection($connection)
    {
        $region = 'email.' . $connection['region'] . '.amazonaws.com';

        set_error_handler(function ($errno, $errstr, $errfile, $errline , $errcontex) {
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
