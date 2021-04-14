<?php

namespace FluentMail\App\Services\Mailer\Providers\TransMail;

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
            if (! Arr::get($connection, 'api_key')) {
                $errors['api_key']['required'] = __('Api key is required.', 'fluent-smtp');
            }

            if (! Arr::get($connection, 'domain_name')) {
                $errors['domain_name']['required'] = __('Domain name is required.', 'fluent-smtp');
            }
        } else if($keyStoreType == 'wp_config') {
            if(!defined('FLUENTMAIL_MAILGUN_API_KEY') || !FLUENTMAIL_MAILGUN_API_KEY) {
                $errors['api_key']['required'] = __('Please define FLUENTMAIL_MAILGUN_API_KEY in wp-config.php file.', 'fluent-smtp');
            }

            if(!defined('FLUENTMAIL_MAILGUN_DOMAIN') || !FLUENTMAIL_MAILGUN_DOMAIN) {
                $errors['domain_name']['required'] = __('Please define FLUENTMAIL_MAILGUN_DOMAIN in wp-config.php file.', 'fluent-smtp');
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }
}
