<?php

namespace FluentMail\App\Services\Mailer\Providers\ToSend;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\ValidatorTrait as BaseValidatorTrait;

trait ValidatorTrait
{
    use BaseValidatorTrait;

    public function validateProviderInformation($connection)
    {
        $errors = [];

        $keyStoreType = $connection['key_store'];

        $apiKey = '';

        if ($keyStoreType == 'db') {
            $apiKey = Arr::get($connection, 'api_key');
            if (!$apiKey) {
                $errors['api_key']['required'] = __('Api key is required.', 'fluent-smtp');
            }
        } else if ($keyStoreType == 'wp_config') {
            if (!defined('FLUENTMAIL_TOSEND_API_KEY') || !FLUENTMAIL_TOSEND_API_KEY) {
                $errors['api_key']['required'] = __('Please define FLUENTMAIL_TOSEND_API_KEY in wp-config.php file.', 'fluent-smtp');
            } else {
                $apiKey = FLUENTMAIL_TOSEND_API_KEY;
            }
        }

        // validate the api key

        if ($apiKey) {
            $senderEmail = Arr::get($connection, 'sender_email');
            if ($senderEmail) {
                $accountInfo = $this->getAccountInfo($apiKey);
                if (is_wp_error($accountInfo)) {
                    $errors['api_key']['required'] = $accountInfo->get_error_message();
                } else {
                    if (empty($accountInfo['verified_domains'])) {
                        $errors['api_key']['required'] = 'No verified domains found in FluentMailer API';
                    } else {
                        $emailDomain = explode('@', $senderEmail)[1];
                        $verifiedDomains = $accountInfo['verified_domains'];
                        if (!in_array($emailDomain, $accountInfo['verified_domains'])) {
                            $errors['sender_email']['required'] = 'Please provide a sender email that match with your verfied emails. Verfied domains: ' . implode(', ', $verifiedDomains) . '.';
                            $errors['api_key']['required'] = 'Please provide a sender email that match with your verfied emails. Verfied domains: ' . implode(', ', $verifiedDomains) . '.';
                        }
                    }
                }
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

    public function getAccountInfo($apiKey)
    {
        $request = wp_remote_get($this->baseUrl . 'info?api_key=' . $apiKey, [
            'sslverify' => false
        ]);

        if (is_wp_error($request)) {
            return $request;
        }

        // remote status code
        $statusCode = wp_remote_retrieve_response_code($request);
        $body = wp_remote_retrieve_body($request);
        $body = json_decode($body, true);

        if (!$body || !is_array($body)) {
            return new \WP_Error('invalid_response', 'Invalid Reponse from remote server');
        }

        if ($statusCode === 200) {

            $verifiedDomains = [];

            foreach (Arr::get($body, 'domains') as $domain) {
                if ($domain['verification_status'] === 'verified') {
                    $verifiedDomains[] = $domain['domain_name'];
                }
            }

            $body['verified_domains'] = $verifiedDomains;

            return $body;
        }

        $message = Arr::get($body, 'message');

        if (!$message) {
            $message = 'Unknown error from remote server';
        }

        return new \WP_Error('invalid_response', $message, $body);

    }
}
