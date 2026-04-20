<?php

namespace FluentMail\App\Services\Mailer\Providers\Cloudflare;

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
            if (!Arr::get($connection, 'account_id')) {
                $errors['account_id']['required'] = __('Cloudflare Account ID is required.', 'fluent-smtp');
            }
        } elseif ($keyStoreType == 'wp_config') {
            if (!defined('FLUENTMAIL_CLOUDFLARE_API_KEY') || !FLUENTMAIL_CLOUDFLARE_API_KEY) {
                $errors['api_key']['required'] = __('Please define FLUENTMAIL_CLOUDFLARE_API_KEY in wp-config.php file.', 'fluent-smtp');
            }
            if (!Arr::get($connection, 'account_id') && (!defined('FLUENTMAIL_CLOUDFLARE_ACCOUNT_ID') || !FLUENTMAIL_CLOUDFLARE_ACCOUNT_ID)) {
                $errors['account_id']['required'] = __('Cloudflare Account ID is required.', 'fluent-smtp');
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

}
