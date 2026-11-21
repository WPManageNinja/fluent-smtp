<?php

namespace FluentMail\App\Services;

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\Mailer\Providers\Factory;
use FluentMail\App\Services\Mailer\Providers\Smtp\Handler as SmtpHandler;
use FluentMail\Includes\Support\ValidationException;

/**
 * Saves one connection: the steps behind the "Save Connection Settings" button, in one place.
 *
 * The admin screen (SettingsController::store()) calls it after it has put back the
 * credentials the browser only saw masked. fluentMailSaveConnection() calls it for code
 * that offers its own settings screen, for example a host that does not show wp-admin.
 *
 * Steps, in order: sanitize the fields, validate them (basic and provider-specific),
 * check the connection with the provider, work out the senders it may send as, filter
 * through `fluentmail_saving_connection_data`, and store.
 */
class ConnectionSaver
{
    /** Credential fields: trimmed, never run through sanitize_text_field(). */
    const SECRET_KEYS = ['password', 'access_key', 'secret_key', 'api_key', 'client_id', 'client_secret', 'auth_token', 'access_token', 'refresh_token'];

    /**
     * @param array  $connection    The connection fields, as the admin screen sends them ('provider', 'sender_email', ...).
     * @param string $connectionKey The key of the connection being edited; empty for a new one.
     * @return string The key the connection is stored under.
     * @throws ValidationException When a field is missing or wrong (errors by field).
     * @throws \Exception          When the provider refuses the connection check, or the provider is unknown.
     */
    public static function save(array $connection, $connectionKey = '')
    {
        $settings = new Settings();
        $provider = fluentMail(Factory::class)->make((string) ($connection['provider'] ?? ''));

        $connection = self::sanitize($connection);

        self::validate($provider, $connection);

        $provider->checkConnection($connection);

        $data = [
            'connection'     => $connection,
            'connection_key' => (string) $connectionKey,
            'valid_senders'  => $provider->getValidSenders($connection),
        ];

        $data = apply_filters('fluentmail_saving_connection_data', $data, $connection['provider']);

        $settings->store($data);

        return $settings->generateUniqueKey($data['connection']['sender_email']);
    }

    /** The field clean-up the admin screen has always done before validating. */
    public static function sanitize(array $connection)
    {
        foreach ($connection as $index => $value) {
            if ($index == 'sender_email') {
                // A percent sequence is valid in a mailbox and is part of a toSend config key's identity.
                if (($connection['provider'] ?? '') === 'tosend' && ($connection['key_store'] ?? '') === 'wp_config') {
                    // Keep the mailbox intact for validateBasicInformation(), including quoted local parts.
                    $connection[$index] = is_string($value) ? trim($value) : '';
                    continue;
                }
                $connection['sender_email'] = sanitize_email($connection['sender_email']);
            }

            if (in_array($index, self::SECRET_KEYS)) {
                if ($value) {
                    $connection[$index] = trim($value);
                }
                continue;
            }

            if (is_string($value) && $value) {
                $connection[$index] = sanitize_text_field($value);

                // Store the name the admin typed. A sender name copied from
                // the site title arrives HTML-escaped, and it is plain text
                // everywhere it is used. fluentMailGetSettings() decodes on
                // read as well, so installs that already hold an escaped
                // name are fixed whether or not they ever save again.
                if ($index === 'sender_name') {
                    $connection[$index] = wp_specialchars_decode($connection[$index], ENT_QUOTES);
                }
            }
        }

        // Store the connection security in the one form the SMTP handler sends with.
        if (($connection['provider'] ?? '') === 'smtp' && array_key_exists('encryption', $connection)) {
            $connection['encryption'] = SmtpHandler::normalizeEncryption($connection['encryption']);
        }

        return $connection;
    }

    /** Basic and provider-specific validation, with every error by field in one exception. */
    public static function validate($provider, array $connection)
    {
        $errors = [];

        try {
            $provider->validateBasicInformation($connection);
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        try {
            $provider->validateProviderInformation($connection);
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->errors());
        }

        if ($errors) {
            throw new ValidationException(esc_html__('Unprocessable Entity', 'fluent-smtp'), 422, null, $errors); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }
}
