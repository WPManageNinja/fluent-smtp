<?php

namespace FluentMail\App\Services;

/**
 * The contract between the server and the admin app about stored credentials.
 *
 * The admin app never receives a secret it already has. Every field listed in
 * SECRET_FIELDS leaves the server as the MASK sentinel instead, and comes back
 * unchanged unless the admin actually typed a new value over it - at which point
 * resolve() puts the stored value back. The OAuth tokens are not even masked; see
 * WITHHELD_FIELDS.
 *
 * Every method here is a pure transform of the array it is given, and returns
 * anything that is not an array untouched.
 */
class SecretMasker
{
    /**
     * The stand-in a stored credential is replaced with on its way to the browser.
     *
     * It has to be a string no credential could ever be, and it has to be truthy:
     * the connection forms read `connection.access_token` to decide whether an OAuth
     * connection is already authenticated, so an empty mask would report every Gmail
     * and Outlook connection as disconnected.
     *
     * The admin app reads it from appVars as `masked_key`, so the two halves of the
     * contract cannot drift apart.
     */
    const MASK = '__MASKED_KEY_ENCRYPTED__';

    /**
     * The connection fields that are never sent to the browser.
     *
     * Masked rather than withheld, because each of these is a field the admin can
     * edit: the form has to be able to say "there is one saved" and to take a new one
     * typed over it. See WITHHELD_FIELDS for the ones that are not sent at all.
     *
     * Wider than the per-provider map that fluentMailSetSettings() encrypts, because
     * the two answer different questions: that one is "what does a DB dump expose",
     * this one is "what must never reach a page an admin can view-source".
     *
     * `auth_token` is deliberately absent. It is the one-time authorization code, and
     * the OAuth handlers blank it the moment they exchange it, so what is in storage
     * is always an empty string - while the field itself is one the admin types into
     * during a re-authentication, where a mask would be in the way.
     *
     * `client_id` is absent too: it identifies the application, it is not a secret,
     * and Google and Microsoft both show it on a public consent screen.
     */
    const SECRET_FIELDS = [
        'password',
        'api_key',
        'secret_key',
        'access_key',
        'client_secret'
    ];

    /**
     * The connection fields the browser does not receive at all - not even a mask.
     *
     * Nobody types an OAuth token. They are issued by Google and Microsoft, written by
     * the handlers that exchange the authorization code, and rewritten by the
     * scheduled refresh; no screen in the plugin has ever offered a field to edit one.
     * A mask would be standing in for a value the admin cannot set, so there is
     * nothing for it to stand in for - these are simply dropped on the way out and put
     * back from storage on the way in.
     *
     * What the screens do need is the one bit `access_token` carries: whether the
     * connection is authenticated. That travels instead as `has_access_token`, which
     * maskConnection() derives and resolve() consumes - and which is also the only
     * thing the browser may say about a token, namely that it wants this one
     * disconnected.
     */
    const WITHHELD_FIELDS = ['access_token', 'refresh_token'];

    /**
     * The alert-channel fields that are never sent to the browser.
     *
     * A Slack or Discord webhook URL belongs on this list as much as a token does -
     * it is a bearer credential, and anyone holding it can post into the channel.
     *
     * Nothing needs resolving on the way back in: every one of these is entered into
     * an empty form and posted to its own register endpoint, so a save always carries
     * a freshly typed value and never a mask.
     */
    const NOTIFICATION_SECRET_FIELDS = ['token', 'site_token', 'webhook_url', 'api_token', 'user_key'];

    /**
     * Replace every stored credential in a settings array with the mask.
     *
     * Call this on anything holding a `connections` array before it is handed to the
     * browser - the localized appVars, and every endpoint that returns connections.
     *
     * @param array $settings
     * @return array
     */
    public static function mask($settings)
    {
        if (empty($settings['connections']) || !is_array($settings['connections'])) {
            return $settings;
        }

        $settings['connections'] = static::maskConnections($settings['connections']);

        return $settings;
    }

    /**
     * Mask the credentials of a connections array.
     *
     * The same job as mask(), for the endpoints that return the connections on their
     * own rather than wrapped in a settings array.
     *
     * @param array $connections
     * @return array
     */
    public static function maskConnections($connections)
    {
        if (empty($connections) || !is_array($connections)) {
            return $connections;
        }

        foreach ($connections as $key => $connection) {
            if (empty($connection['provider_settings']) || !is_array($connection['provider_settings'])) {
                continue;
            }

            $connections[$key]['provider_settings'] = static::maskConnection($connection['provider_settings']);
        }

        return $connections;
    }

    /**
     * Everything one connection's settings must have done to them before they are sent
     * to the browser: the editable credentials masked, the OAuth tokens removed.
     *
     * `has_access_token` replaces the token itself. It is derived here rather than
     * stored, it is 'yes' only when there is a token, and it is the only shape the
     * screens ever see of one - see WITHHELD_FIELDS. It is set only when the
     * connection actually has the field, so a connection to a provider with no OAuth
     * in it does not grow a flag about a token it will never hold.
     *
     * @param array $providerSettings
     * @return array
     */
    public static function maskConnection($providerSettings)
    {
        if (!is_array($providerSettings)) {
            return $providerSettings;
        }

        $providerSettings = static::maskFields($providerSettings);

        if (array_key_exists('access_token', $providerSettings)) {
            $providerSettings['has_access_token'] = empty($providerSettings['access_token']) ? 'no' : 'yes';
        }

        foreach (static::WITHHELD_FIELDS as $field) {
            unset($providerSettings[$field]);
        }

        return $providerSettings;
    }

    /**
     * Mask the secret fields of a single flat array of settings.
     *
     * Only a non-empty string is masked. An absent or empty credential stays empty, so
     * the forms can still tell "nothing saved yet" from "saved, not shown" - and so a
     * field the admin clears stays cleared rather than being masked back into place.
     *
     * @param array $values
     * @param array|null $fields defaults to SECRET_FIELDS
     * @return array
     */
    public static function maskFields($values, $fields = null)
    {
        if (!is_array($values)) {
            return $values;
        }

        $fields = is_null($fields) ? static::SECRET_FIELDS : $fields;

        foreach ($fields as $field) {
            if (!empty($values[$field]) && is_string($values[$field])) {
                $values[$field] = static::MASK;
            }
        }

        return $values;
    }

    /**
     * Put the stored credentials back into a payload that came from the browser.
     *
     * Must run before anything validates or tests the connection, so that a save that
     * did not touch the key is still checked against the real one.
     *
     * Three outcomes per masked field:
     *
     *  - the value is the mask   -> the admin did not touch it, restore what is stored
     *  - the value is anything else -> the admin typed it, take it as given
     *  - the value is the mask but nothing is stored -> drop it to an empty string
     *
     * That last case should not happen from our own forms, and the empty string is
     * what makes it safe when it does: the provider's own "this field is required"
     * validation then fires, rather than the sentinel being saved and used as a
     * credential.
     *
     * The withheld fields are simpler, because they are not editable: whatever arrived
     * under those names is dropped and the stored value put back, with `has_access_token`
     * as the single exception that can ask for a token to be cleared.
     *
     * @param array $values the submitted settings
     * @param array $stored the settings currently saved for this connection
     * @param array|null $fields defaults to SECRET_FIELDS
     * @return array
     */
    public static function resolve($values, $stored, $fields = null)
    {
        if (!is_array($values)) {
            return $values;
        }

        $fields = is_null($fields) ? static::SECRET_FIELDS : $fields;
        $stored = is_array($stored) ? $stored : [];

        foreach ($fields as $field) {
            if (!isset($values[$field]) || $values[$field] !== static::MASK) {
                continue;
            }

            $values[$field] = isset($stored[$field]) && is_string($stored[$field])
                ? $stored[$field]
                : '';
        }

        /*
         * The OAuth tokens never left the server, so whatever arrived under these names
         * is discarded outright rather than trusted - a request cannot set a token by
         * naming one. What is stored is what stays stored.
         */
        foreach (static::WITHHELD_FIELDS as $field) {
            unset($values[$field]);

            if (array_key_exists($field, $stored)) {
                $values[$field] = $stored[$field];
            }
        }

        /*
         * The single exception, and the only thing the browser may say about a token:
         * "disconnect this one". It is what the re-authenticate link on the Gmail and
         * Outlook forms sets, and clearing the access token is what sends the next save
         * back through the authorization-code exchange - which issues a new refresh
         * token of its own, so that one is left in place for the exchange to replace.
         *
         * The flag is derived on the way out and consumed here; it is never stored.
         */
        if (array_key_exists('has_access_token', $values)) {
            if ($values['has_access_token'] !== 'yes') {
                $values['access_token'] = '';
            }

            unset($values['has_access_token']);
        }

        return $values;
    }
}
