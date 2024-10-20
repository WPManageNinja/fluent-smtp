<?php

namespace FluentMail\App\Services\Mailer;

use FluentMail\App\Models\Settings;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Support\ValidationException;

trait ValidatorTrait
{
    public function validateBasicInformation($connection)
    {
        $errors = [];

        if (!($email = Arr::get($connection, 'sender_email'))) {
            $errors['sender_email']['required'] = __('Sender email is required.', 'fluent-smtp');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['sender_email']['email'] = __('Invalid email address.', 'fluent-smtp');
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

    public function validateProviderInformation($inputs)
    {
        // Required Method
    }

    public function throwValidationException($errors)
    {
        throw new ValidationException(
            esc_html__('Unprocessable Entity', 'fluent-smtp'), 422, null, $errors // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        );
    }
}
