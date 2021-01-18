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
            $errors['host']['required'] = __('SMTP host is required.', 'fluent-smtp');
        }

        if (! Arr::get($connection, 'port')) {
            $errors['port']['required'] = __('SMTP port is required.', 'fluent-smtp');
        }

        if (Arr::get($connection, 'auth') == 'yes') {
            if ( !Arr::get($connection, 'username')) {
                $errors['username']['required'] = __('SMTP username is required.', 'fluent-smtp');
            }

            if ( !Arr::get($connection, 'password')) {
                $errors['password']['required'] = __('SMTP password is required.', 'fluent-smtp');
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }
}
