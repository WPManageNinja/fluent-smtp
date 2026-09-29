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

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong.', 'fluent-smtp'), []));
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
        $data = $this->getSetting();

        $accessToken = $this->getAccessToken($data);

        $api = (new API($data['client_id'], $data['client_secret'], Arr::get($data, 'tenant_id')));

        /*
         * Two ways to hand Graph a message, and neither covers everything.
         *
         * Raw MIME keeps every header PHPMailer built - In-Reply-To and
         * References for threading, List-Unsubscribe, whatever a plugin added -
         * but Graph takes its recipients from To and Cc alone and does not
         * deliver to a Bcc header in MIME content. Our wp_mail() leaves
         * PHPMailer in mail() mode, which writes that header, so a Bcc arrived
         * at Graph and went nowhere.
         *
         * The structured payload carries Bcc as its own bccRecipients field,
         * which Graph honours, but it accepts custom headers only under an x-
         * prefix. So the message stays on the MIME path unless it has a Bcc,
         * which is the one thing that path cannot do.
         */
        if ($this->getParam('headers.bcc')) {
            $result = $api->sendMail($this->buildGraphMessage(), $accessToken);
        } else {
            $rawMessage = $this->normalizeListHeaders(
                $this->phpMailer->getSentMIMEMessage()
            );

            $result = $api->sendMime(chunk_split(base64_encode($rawMessage), 76, "\n"), $accessToken);
        }

        if(is_wp_error($result)) {
            $errorMessage = $result->get_error_message();
            return new \WP_Error(422, $errorMessage, []);
        } else {
            return array(
                'RequestId' => $result['request-id'],
            );
        }

    }

    /**
     * The sendMail request body for the message PHPMailer holds, in Graph's
     * JSON shape. Mirrors what the MIME path sends: the same From, the same
     * recipients, and the attachments in the same disposition.
     *
     * @return array
     */
    protected function buildGraphMessage()
    {
        $contentType = $this->getHeader('content-type');

        $message = [
            'subject'      => $this->getSubject(),
            'body'         => [
                'contentType' => in_array($contentType, ['text/html', 'multipart/alternative'], true) ? 'HTML' : 'Text',
                'content'     => (string)$this->getParam('message')
            ],
            'from'         => $this->graphRecipient($this->phpMailer->From, $this->phpMailer->FromName),
            'toRecipients' => $this->graphRecipients($this->getParam('to')),
        ];

        if ($cc = $this->graphRecipients($this->getParam('headers.cc'))) {
            $message['ccRecipients'] = $cc;
        }

        if ($bcc = $this->graphRecipients($this->getParam('headers.bcc'))) {
            $message['bccRecipients'] = $bcc;
        }

        if ($replyTo = $this->graphRecipients($this->getParam('headers.reply-to'))) {
            $message['replyTo'] = $replyTo;
        }

        if ($headers = $this->graphInternetMessageHeaders()) {
            $message['internetMessageHeaders'] = $headers;
        }

        if ($attachments = $this->graphAttachments()) {
            $message['attachments'] = $attachments;
        }

        return ['message' => $message];
    }

    /**
     * @param array $recipients Rows of ['email' => ..., 'name' => ...] as setAttributes() builds them
     * @return array
     */
    private function graphRecipients($recipients)
    {
        $list = [];

        foreach ((array)$recipients as $recipient) {
            if (empty($recipient['email'])) {
                continue;
            }

            $list[] = $this->graphRecipient($recipient['email'], Arr::get($recipient, 'name'));
        }

        return $list;
    }

    private function graphRecipient($email, $name = '')
    {
        $address = ['address' => $email];

        if ($name) {
            $address['name'] = $name;
        }

        return ['emailAddress' => $address];
    }

    /**
     * Graph refuses an internetMessageHeaders entry whose name does not start
     * with x-, so only those can travel on this path. Anything else that a
     * plugin added is dropped here rather than failing the whole send.
     *
     * @return array
     */
    private function graphInternetMessageHeaders()
    {
        $headers = [];

        foreach ((array)$this->getParam('custom_headers') as $header) {
            $name = trim((string)Arr::get($header, 'key'));
            $value = trim((string)Arr::get($header, 'value'));

            if ($value === '' || stripos($name, 'x-') !== 0) {
                continue;
            }

            $headers[] = [
                'name'  => $name,
                'value' => $value
            ];
        }

        return $headers;
    }

    /**
     * PHPMailer's attachment rows as Graph fileAttachment objects. An inline
     * image keeps its Content-ID so the HTML body can still refer to it.
     *
     * @return array
     */
    private function graphAttachments()
    {
        $attachments = [];

        foreach ((array)$this->getParam('attachments') as $attachment) {
            $isString = !empty($attachment[5]);

            if ($isString) {
                $content = (string)$attachment[0];
            } else {
                try {
                    $content = $this->secureFileRead($attachment[0]);
                } catch (\Exception $e) {
                    $this->logAttachmentFailure('Outlook', $e);
                    continue;
                }
            }

            $row = [
                '@odata.type'  => '#microsoft.graph.fileAttachment',
                'name'         => $this->getAttachmentName($attachment),
                'contentType'  => $this->attachmentContentType($attachment, $isString),
                'contentBytes' => base64_encode($content)
            ];

            if (isset($attachment[6]) && $attachment[6] === 'inline' && !empty($attachment[7])) {
                $row['isInline'] = true;
                $row['contentId'] = $attachment[7];
            }

            $attachments[] = $row;
        }

        return $attachments;
    }

    /**
     * @param array $attachment One row of PHPMailer::getAttachments()
     * @param bool $isString Whether index 0 is the content rather than a path
     * @return string
     */
    private function attachmentContentType($attachment, $isString)
    {
        if (!empty($attachment[4])) {
            return $attachment[4];
        }

        if (!$isString && function_exists('mime_content_type')) {
            $type = @mime_content_type($attachment[0]);

            if ($type) {
                return $type;
            }
        }

        return 'application/octet-stream';
    }

    public function validateProviderInformation($connection)
    {
        $errors = [];

        $keyStoreType = $connection['key_store'];

        $clientId = Arr::get($connection, 'client_id');
        $clientSecret = Arr::get($connection, 'client_secret');

        if (!API::isValidTenant(Arr::get($connection, 'tenant_id'))) {
            $errors['tenant_id']['invalid'] = __('Directory (tenant) ID must be the tenant GUID, a verified domain such as contoso.onmicrosoft.com, or one of common, organizations, consumers.', 'fluent-smtp');
        }

        if ($keyStoreType == 'db') {
            if (!$clientId) {
                $errors['client_id']['required'] = __('Application Client ID is required.', 'fluent-smtp');
            }

            if (!$clientSecret) {
                $errors['client_secret']['required'] = __('Application Client Secret is required.', 'fluent-smtp');
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

        /*
         * Tokens are issued by one authority and mean nothing at another, so a
         * tenant change invalidates whatever is already stored. Say so instead
         * of saving a connection whose credentials can now only fail at send
         * time, with a 401 that reads as an unrelated problem. Re-authenticating
         * posts the form as it stands, so the new tenant is used for the sign-in
         * without needing this save to land first.
         */
        if (!$authToken && $accessToken && $this->tenantChanged($connection)) {
            $errors['tenant_id']['reauth'] = __('The Directory (tenant) ID changed, so the existing Microsoft authentication no longer applies. Please authenticate with Office365 again before saving.', 'fluent-smtp');
            $this->throwValidationException($errors);
        }

        if (!$accessToken && $authToken) {
            $tokens = (new API($clientId, $clientSecret, Arr::get($connection, 'tenant_id')))->generateToken($authToken);
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
            $errors['auth_token']['required'] = __('Please provide an auth token.', 'fluent-smtp');
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }
    }

    /**
     * Whether the submitted connection points at a different Microsoft
     * authority than the one its stored tokens were issued by.
     *
     * Compares resolved values, so switching between an empty field and an
     * explicit `common` — which address the same authority — is not treated as
     * a change that costs the admin a sign-in.
     *
     * @param array $connection Submitted provider_settings.
     * @return bool
     */
    private function tenantChanged($connection)
    {
        $senderEmail = Arr::get($connection, 'sender_email');

        if (!$senderEmail) {
            return false;
        }

        $stored = (new Settings())->getConnection($senderEmail);

        if (Arr::get($stored, 'provider_settings.provider') !== 'outlook') {
            return false;
        }

        return API::resolveTenant(Arr::get($stored, 'provider_settings.tenant_id'))
            !== API::resolveTenant(Arr::get($connection, 'tenant_id'));
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
            $fluentAPi = (new API($config['client_id'], $config['client_secret'], Arr::get($config, 'tenant_id')));

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
            'content' => sprintf(
                /* translators: %s: number of minutes until the access token expires */
                __('Valid (%s minutes)', 'fluent-smtp'),
                number_format_i18n(intval(((Arr::get($connection, 'expire_stamp') - time()) / 60)))
            )
        ];

        if ($tokenError) {
            $extraRow['content'] = $tokenError;
        } elseif (Arr::get($connection, 'expire_stamp') < time()) {
            $extraRow['content'] = __('Invalid. Please authenticate again.', 'fluent-smtp');
        }

        $connection['extra_rows'] = [$extraRow];

        return [
            'info' => (string)fluentMail('view')->make('admin.general_connection_info', [
                'connection' => $connection
            ])
        ];
    }
}
