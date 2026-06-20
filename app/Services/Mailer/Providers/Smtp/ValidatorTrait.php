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

        $keyStoreType = Arr::get($connection, 'key_store', 'db');
        $serial = Arr::get($connection, 'connection_id');

        if ($keyStoreType == 'wp_config' && !is_null($serial)) {
            $suffix = '_' . $serial;

            $hostConst     = 'FLUENTMAIL_SMTP_HOST' . $suffix;
            $usernameConst = 'FLUENTMAIL_SMTP_USERNAME' . $suffix;
            $passwordConst = 'FLUENTMAIL_SMTP_PASSWORD' . $suffix;

            if (!defined($hostConst) || !constant($hostConst)) {
                $errors['host']['required'] = sprintf(__('Please define %s in wp-config.php.', 'fluent-smtp'), $hostConst);
            }
            if (!defined($usernameConst) || !constant($usernameConst)) {
                $errors['username']['required'] = sprintf(__('Please define %s in wp-config.php.', 'fluent-smtp'), $usernameConst);
            }
            if (!defined($passwordConst) || !constant($passwordConst)) {
                $errors['password']['required'] = sprintf(__('Please define %s in wp-config.php.', 'fluent-smtp'), $passwordConst);
            }

            // Validate sender_email if username is not a valid email
            if (defined($usernameConst) && !is_email(constant($usernameConst))) {
                $senderConst = 'FLUENTMAIL_SMTP_SENDER_EMAIL' . $suffix;
                if (!defined($senderConst) || !is_email(constant($senderConst))) {
                    $errors['sender_email']['required'] = sprintf(
                        __('Username is not an email. Please define %s in wp-config.php.', 'fluent-smtp'),
                        $senderConst
                    );
                }
            }
        } else {
            if (!Arr::get($connection, 'host')) {
                $errors['host']['required'] = __('SMTP host is required.', 'fluent-smtp');
            }

            if (!Arr::get($connection, 'port')) {
                $errors['port']['required'] = __('SMTP port is required.', 'fluent-smtp');
            }

            if (Arr::get($connection, 'auth') == 'yes') {
                if ($keyStoreType == 'wp_config') {
                    if (!defined('FLUENTMAIL_SMTP_USERNAME') || !FLUENTMAIL_SMTP_USERNAME) {
                        $errors['username']['required'] = __('Please define FLUENTMAIL_SMTP_USERNAME in wp-config.php file.', 'fluent-smtp');
                    }

                    if (!defined('FLUENTMAIL_SMTP_PASSWORD') || !FLUENTMAIL_SMTP_PASSWORD) {
                        $errors['password']['required'] = __('Please define FLUENTMAIL_SMTP_PASSWORD in wp-config.php file.', 'fluent-smtp');
                    }
                } else {
                    if (!Arr::get($connection, 'username')) {
                        $errors['username']['required'] = __('SMTP username is required.', 'fluent-smtp');
                    }

                    if (!Arr::get($connection, 'password')) {
                        $errors['password']['required'] = __('SMTP password is required.', 'fluent-smtp');
                    }
                }
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }
}
