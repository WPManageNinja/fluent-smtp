<?php

namespace FluentMail\App\Services\Mailer\Providers\SendGrid;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;

trait ValidatorTrait
{
    use BaseValidatorTrait;
    
    public function validateProviderInformation($connection)
    {
        $errors = [];

        if (! Arr::get($connection, 'api_key')) {
            $errors['api_key']['required'] = 'Api key is required.';
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
