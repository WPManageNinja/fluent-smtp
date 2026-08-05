<?php

namespace FluentMail\App\Services\Mailer\Providers\Outlook;

use FluentMail\App\Models\Settings;
use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{

    public function send()
    {
        $this->phpMailer->Encoding = 'base64';

        if ($this->preSend() && $this->phpMailer->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong!', 'fluent-smtp'), []));
    }

    protected function postSend()
    {
        try {
            $returnResponse = $this->sendViaApi();
        } catch (\Exception $e) {
            $returnResponse = new \WP_Error(422, $e->getMessage(), []);
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        $this->settings = self::withResolvedKeys($settings);

        return $this;
    }

    /**
     * The client id/secret can live in wp-config.php instead of the database,
     * in which case the stored connection carries empty values for both.
     *
     * @param array $settings
     * @return array
     */
    private static function withResolvedKeys($settings)
    {
        if (Arr::get($settings, 'key_store') == 'wp_config') {
            $settings['client_id'] = defined('FLUENTMAIL_OUTLOOK_CLIENT_ID') ? FLUENTMAIL_OUTLOOK_CLIENT_ID : '';
            $settings['client_secret'] = defined('FLUENTMAIL_OUTLOOK_CLIENT_SECRET') ? FLUENTMAIL_OUTLOOK_CLIENT_SECRET : '';
        }

        return $settings;
    }

    /**
     * Renew the access token for a connection outside of a send, so an idle
     * site cannot let the refresh token age out unnoticed.
     *
     * @param array $connection provider_settings of the connection
     * @return true|\WP_Error
     */
    public function renewToken($connection)
    {
        try {
            $this->getAccessToken(self::withResolvedKeys($connection), true);
            return true;
        } catch (\Exception $e) {
            return new \WP_Error('token_renew_failed', $e->getMessage());
        }
    }

    private function sendViaApi()
    {
        $rawMessage = $this->normalizeListHeaders(
            $this->phpMailer->getSentMIMEMessage()
        );

        $mime = chunk_split(base64_encode($rawMessage), 76, "\n");

        $data = $this->getSetting();

        $accessToken = $this->getAccessToken($data);

        $api = (new API($data['client_id'], $data['client_secret']));

        $result = $api->sendMime($mime, $accessToken);

        if(is_wp_error($result)) {
            $errorMessage = $result->get_error_message();
            return new \WP_Error(422, $errorMessage, []);
        } else {
            return array(
                'RequestId' => $result['request-id'],
            );
        }

    }

    public function validateProviderInformation($connection)
    {
        $errors = [];

        $keyStoreType = $connection['key_store'];

        $clientId = Arr::get($connection, 'client_id');
        $clientSecret = Arr::get($connection, 'client_secret');

        if ($keyStoreType == 'db') {
            if (!$clientId) {
                $errors['client_id']['required'] = __('Application Client ID is required.', 'fluent-smtp');
            }

            if (!$clientSecret) {
                $errors['client_secret']['required'] = __('Application Client Secret key is required.', 'fluent-smtp');
            }
        } else if ($keyStoreType == 'wp_config') {
            if (!defined('FLUENTMAIL_OUTLOOK_CLIENT_ID') || !FLUENTMAIL_OUTLOOK_CLIENT_ID) {
                $errors['client_id']['required'] = __('Please define FLUENTMAIL_OUTLOOK_CLIENT_ID in wp-config.php file.', 'fluent-smtp');
            } else {
                $clientId = FLUENTMAIL_OUTLOOK_CLIENT_ID;
            }

            if (!defined('FLUENTMAIL_OUTLOOK_CLIENT_SECRET') || !FLUENTMAIL_OUTLOOK_CLIENT_SECRET) {
                $errors['client_secret']['required'] = __('Please define FLUENTMAIL_OUTLOOK_CLIENT_SECRET in wp-config.php file.', 'fluent-smtp');
            } else {
                $clientSecret = FLUENTMAIL_OUTLOOK_CLIENT_SECRET;
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }

        $accessToken = Arr::get($connection, 'access_token');
        $authToken = Arr::get($connection, 'auth_token');

        if (!$accessToken && $authToken) {
            $tokens = (new API($clientId, $clientSecret))->generateToken($authToken);
            if (is_wp_error($tokens)) {
                $errors['auth_token']['required'] = $tokens->get_error_message();
            } else {
                add_filter('fluentmail_saving_connection_data', function ($con, $provider) use ($connection, $tokens) {

                    if ($provider != 'outlook') {
                        return $con;
                    }

                    if (Arr::get($con, 'connection.sender_email') != $connection['sender_email']) {
                        return $con;
                    }

                    $con['connection']['refresh_token'] = $tokens['refresh_token'];
                    $con['connection']['access_token'] = $tokens['access_token'];
                    $con['connection']['auth_token'] = '';
                    $con['connection']['expire_stamp'] = time() + $tokens['expires_in'];

                    return $con;
                }, 10, 2);
            }
        } else if (!$authToken && !$accessToken) {
            $errors['auth_token']['required'] = __('Please Provide Auth Token.', 'fluent-smtp');
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

    private function saveNewTokens($existingData, $tokens)
    {
        if (empty($tokens['access_token'])) {
            return false;
        }

        $senderEmail = $existingData['sender_email'];

        $existingData['access_token'] = $tokens['access_token'];

        /*
         * A refresh response does not have to carry a new refresh token. When
         * it does not, the one we already hold stays valid - so it is kept
         * rather than overwritten. Bailing out here (as this used to) threw
         * away a perfectly good access token as well, which left expire_stamp
         * in the past and made every single send perform its own refresh. That
         * eventually trips the identity server's throttling, and the
         * connection looks dead for reasons nothing reports.
         */
        if (!empty($tokens['refresh_token'])) {
            $existingData['refresh_token'] = $tokens['refresh_token'];
        }

        $expiresIn = !empty($tokens['expires_in']) ? (int)$tokens['expires_in'] : 3600;
        $existingData['expire_stamp'] = $expiresIn + time();
        $existingData['expires_in'] = $expiresIn;

        (new Settings())->updateConnection($senderEmail, $existingData);

        fluentMailGetProvider($senderEmail, true); // we are clearing the static cache here

        // Keep the token warm even on a site that is not sending, so an idle
        // stretch cannot quietly age the refresh token out. See Gmail, which
        // has had this since day one.
        wp_schedule_single_event($existingData['expire_stamp'] - 360, 'fluentsmtp_renew_outlook_token');

        return true;
    }

    private function getAccessToken($config, $force = false)
    {
        $accessToken = Arr::get($config, 'access_token');
        $expireStamp = (int)Arr::get($config, 'expire_stamp');

        // check if expired or will be expired in 300 seconds
        if ($force || ($expireStamp - 300) < time()) {
            $fluentAPi = (new API($config['client_id'], $config['client_secret']));

            $tokens = $fluentAPi->sendTokenRequest('refresh_token', [
                'refresh_token' => Arr::get($config, 'refresh_token')
            ]);

            /*
             * This used to return false, and the caller then handed `false` to
             * the Graph API as the bearer token. The send failed with a generic
             * 401 while the real reason - an expired or revoked refresh token,
             * fixable only by reconnecting the account - was discarded here and
             * never reached the log or the admin.
             */
            if (is_wp_error($tokens)) {
                throw new \Exception(
                    sprintf(
                    /* translators: %s: error message returned by Microsoft */
                        __('Could not renew the Microsoft access token: %s. Please reconnect this Outlook connection in FluentSMTP settings.', 'fluent-smtp'),
                        $tokens->get_error_message()
                    )
                );
            }

            $this->saveNewTokens($config, $tokens);

            $accessToken = Arr::get($tokens, 'access_token');
        }

        if (empty($accessToken)) {
            throw new \Exception(
                __('No usable Microsoft access token is available for this connection. Please reconnect this Outlook connection in FluentSMTP settings.', 'fluent-smtp')
            );
        }

        return $accessToken;
    }

    public function getConnectionInfo($connection)
    {
        $connection = self::withResolvedKeys($connection);

        $tokenError = '';

        try {
            $this->getAccessToken($connection);
        } catch (\Exception $e) {
            // Reporting why the token could not be renewed is the whole point
            // of this panel, so a failure here is shown rather than thrown.
            $tokenError = $e->getMessage();
        }

        $info = fluentMailgetConnection($connection['sender_email']);
        $connection = $info->getSetting();

        $extraRow = [
            'title'   => __('Token Validity', 'fluent-smtp'),
            'content' => 'Valid (' . intval(((Arr::get($connection, 'expire_stamp') - time()) / 60)) . 'm)'
        ];

        if ($tokenError) {
            $extraRow['content'] = $tokenError;
        } elseif (Arr::get($connection, 'expire_stamp') < time()) {
            $extraRow['content'] = 'Invalid. Please re-authenticate';
        }

        $connection['extra_rows'] = [$extraRow];

        return [
            'info' => (string)fluentMail('view')->make('admin.general_connection_info', [
                'connection' => $connection
            ])
        ];
    }
}
