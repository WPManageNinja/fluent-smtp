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
            $additionalSenders = array_filter((array) Arr::get($connection, 'additional_senders', []));

            if ($senderEmail || $additionalSenders) {
                $accountInfo = $this->getAccountInfo($apiKey);
                if (is_wp_error($accountInfo)) {
                    $errors['api_key']['required'] = $accountInfo->get_error_message();
                } else {
                    if (empty($accountInfo['verified_domains'])) {
                        $errors['api_key']['required'] = __('No verified domains found for this toSend API key.', 'fluent-smtp');
                    } else {
                        $verifiedDomains = $accountInfo['verified_domains'];
                        $domainListMsg = sprintf(
                            __('Verified domains on this API key: %s.', 'fluent-smtp'),
                            implode(', ', $verifiedDomains)
                        );

                        if ($senderEmail) {
                            $emailDomain = substr(strrchr($senderEmail, '@'), 1);
                            if (!$emailDomain || !in_array($emailDomain, $verifiedDomains)) {
                                $errors['sender_email']['required'] = __('Sender email domain is not verified in toSend. ', 'fluent-smtp') . $domainListMsg;
                            }
                        }

                        $invalidAdditional = [];
                        foreach ($additionalSenders as $extra) {
                            if (!is_email($extra)) {
                                $invalidAdditional[] = $extra . ' ' . __('(invalid format)', 'fluent-smtp');
                                continue;
                            }
                            $extraDomain = substr(strrchr($extra, '@'), 1);
                            if (!$extraDomain || !in_array($extraDomain, $verifiedDomains)) {
                                $invalidAdditional[] = $extra;
                            }
                        }

                        if ($invalidAdditional) {
                            $errors['additional_senders']['required'] = __('These additional senders are not on a verified toSend domain: ', 'fluent-smtp') . implode(', ', $invalidAdditional) . '. ' . $domainListMsg;
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
