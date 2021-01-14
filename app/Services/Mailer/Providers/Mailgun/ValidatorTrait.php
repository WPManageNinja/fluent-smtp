<?php

namespace FluentMail\App\Services\Mailer\Providers\Mailgun;

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

        if (! Arr::get($connection, 'domain_name')) {
            $errors['domain_name']['required'] = 'Domain name is required.';
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }
}
