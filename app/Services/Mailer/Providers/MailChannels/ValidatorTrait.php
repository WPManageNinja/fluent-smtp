<?php

namespace FluentMail\App\Services\Mailer\Providers\MailChannels;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;

trait ValidatorTrait
{
    use BaseValidatorTrait;

    public function validateProviderInformation($connection)
    {
        $errors = [];
        $keyStore = Arr::get($connection, 'key_store', 'db');
        $mode = Arr::get($connection, 'send_mode', 'direct');

        if (!in_array($mode, ['direct', 'queued'], true)) {
            $errors['send_mode']['required'] = __('Select direct or queued submission.', 'fluent-smtp');
        }

        if ($keyStore === 'db') {
            if (!Arr::get($connection, 'api_key')) {
                $errors['api_key']['required'] = __('API key is required.', 'fluent-smtp');
            }
        } elseif ($keyStore === 'wp_config' && !$this->getConfiguredApiKey()) {
            $errors['api_key']['required'] = __('Define FLUENTMAIL_MAILCHANNELS_API_KEY or MAILCHANNELS_API_KEY in wp-config.php.', 'fluent-smtp');
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }
}
