<?php

namespace FluentMail\App\Services\Mailer\Providers\AmazonSes;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;

trait ValidatorTrait
{
    use BaseValidatorTrait;

    protected $identityLookupAccessDeniedFor = '';

    public function validateProviderInformation($connection)
    {
        $errors = [];

        $keyStoreType = $connection['key_store'];

        if ($keyStoreType == 'db') {
            if (!Arr::get($connection, 'access_key')) {
                $errors['access_key']['required'] = __('Access key is required.', 'fluent-smtp');
            }

            if (!Arr::get($connection, 'secret_key')) {
                $errors['secret_key']['required'] = __('Secret key is required.', 'fluent-smtp');
            }
        } else if ($keyStoreType == 'wp_config') {
            if (!defined('FLUENTMAIL_AWS_ACCESS_KEY_ID') || !FLUENTMAIL_AWS_ACCESS_KEY_ID) {
                $errors['access_key']['required'] = __('Please define FLUENTMAIL_AWS_ACCESS_KEY_ID in wp-config.php file.', 'fluent-smtp');
            }

            if (!defined('FLUENTMAIL_AWS_SECRET_ACCESS_KEY') || !FLUENTMAIL_AWS_SECRET_ACCESS_KEY) {
                $errors['secret_key']['required'] = __('Please define FLUENTMAIL_AWS_SECRET_ACCESS_KEY in wp-config.php file.', 'fluent-smtp');
            }
        }

        if (!SimpleEmailService::isValidRegion(Arr::get($connection, 'region'))) {
            $errors['region']['invalid'] = __('Please select a valid Amazon SES region.', 'fluent-smtp');
            // The existing SES form renders connection errors in this slot.
            $errors['api_error'] = $errors['region']['invalid'];
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

    public function checkConnection($connection)
    {
        $connection = $this->filterConnectionVars($connection);
        $ses = $this->makeSesClient($connection, true);

        $lists = $ses->listVerifiedEmailAddresses();

        return $this->validateIdentityLookupResult($lists, $connection);
    }

    protected function validateIdentityLookupResult($lists, $connection)
    {
        $this->identityLookupAccessDeniedFor = '';

        if (!is_wp_error($lists)) {
            return true;
        }

        $errorData = $lists->get_error_data();
        $errorCode = isset($errorData['Error']['Code']) ? $errorData['Error']['Code'] : '';

        if ($errorCode === 'AccessDenied') {
            $this->identityLookupAccessDeniedFor = $this->identityLookupKey($connection);
            return true;
        }

        $this->throwValidationException(['api_error' => $lists->get_error_message()]);
    }

    protected function consumeIdentityLookupAccessDenied($connection)
    {
        $deniedFor = $this->identityLookupAccessDeniedFor;
        $this->identityLookupAccessDeniedFor = '';

        return $deniedFor && hash_equals($deniedFor, $this->identityLookupKey($connection));
    }

    protected function identityLookupKey($connection)
    {
        return hash('sha256', serialize([
            (string)Arr::get($connection, 'access_key'),
            (string)Arr::get($connection, 'secret_key'),
            (string)Arr::get($connection, 'region'),
            (string)Arr::get($connection, 'sender_email'),
        ]));
    }

    protected function makeSesClient($connection, $triggerError)
    {
        return new SimpleEmailService(
            $connection['access_key'],
            $connection['secret_key'],
            SimpleEmailService::regionToHost($connection['region']),
            $triggerError
        );
    }
}
