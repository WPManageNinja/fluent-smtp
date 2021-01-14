<?php

namespace FluentMail\App\Services\Mailer\Providers\Smtp;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;

trait ValidatorTrait
{
    use BaseValidatorTrait;
    
    public function validateProviderInformation($connection)
    {
        $errors = [];

        if (! Arr::get($connection, 'host')) {
            $errors['host']['required'] = 'SMTP host is required.';
        }

        if (! Arr::get($connection, 'port')) {
            $errors['port']['required'] = 'SMTP port is required.';
        }

        if (Arr::get($connection, 'auth') == 'yes') {
            if ( !Arr::get($connection, 'username')) {
                $errors['username']['required'] = 'SMTP username is required.';
            }

            if ( !Arr::get($connection, 'password')) {
                $errors['password']['required'] = 'SMTP password is required.';
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }
}
