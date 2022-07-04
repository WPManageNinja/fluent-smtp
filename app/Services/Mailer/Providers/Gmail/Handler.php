<?php

namespace FluentMail\App\Services\Mailer\Providers\Gmail;

use FluentMail\App\Models\Settings;
use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{

    public function send()
    {
        if ($this->preSend() && $this->phpMailer->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(423, 'Something went wrong!', []));
    }

    protected function postSend()
    {
        try {
            $returnResponse = $this->sendViaApi();
        } catch (\Exception $e) {
            $returnResponse = new \WP_Error(423, $e->getMessage(), []);
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if (Arr::get($settings, 'key_store') == 'wp_config') {
            $settings['client_id'] = defined('FLUENTMAIL_GMAIL_CLIENT_ID') ? FLUENTMAIL_GMAIL_CLIENT_ID : '';
            $settings['client_secret'] = defined('FLUENTMAIL_GMAIL_CLIENT_SECRET') ? FLUENTMAIL_GMAIL_CLIENT_SECRET : '';
        }

        $this->settings = $settings;

        return $this;
    }

    private function sendViaApi()
    {
        if (!class_exists('\Google_Service_Gmail_Message')) {
            require_once FLUENTMAIL_PLUGIN_PATH . 'includes/libs/google-api-client/vendor/autoload.php';
        }

        $message = $this->phpMailer->getSentMIMEMessage();

        $data = $this->getSetting();

        $googleApiMessage = new \Google_Service_Gmail_Message();

        $file_size = strlen($message);
        $googleClient = $this->getApiClient($data);
        $googleService = new \Google_Service_Gmail($googleClient);

        $result = array();
        try {
            $googleClient->setDefer(true);
            $result = $googleService->users_messages->send('me', $googleApiMessage, array('uploadType' => 'resumable'));

            $chunkSizeBytes = 1 * 1024 * 1024;

            // create mediafile upload
            $media = new \Google_Http_MediaFileUpload(
                $googleClient,
                $result,
                'message/rfc822',
                $message,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize($file_size);

            $status = false;
            while (!$status) {
                $status = $media->nextChunk();
            }
            $result = false;

            // Reset to the client to execute requests immediately in the future.
            $googleClient->setDefer(false);

            $googleMessageId = $status->getId();

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            return new \WP_Error(423, $errorMessage, []);
        }

        return array(
            'MessageId' => $googleMessageId,
        );
    }

    public function validateProviderInformation($connection)
    {
        $errors = [];

        $keyStoreType = $connection['key_store'];

        $clientId = Arr::get($connection, 'client_id');
        $clientSecret = Arr::get($connection, 'client_secret');

        if ($keyStoreType == 'db') {
            if (!$clientId) {
                $errors['client_id']['required'] = __('Application Cluent ID is required.', 'fluent-smtp');
            }

            if (!$clientSecret) {
                $errors['client_secret']['required'] = __('Application Client Secret key is required.', 'fluent-smtp');
            }
        } else if ($keyStoreType == 'wp_config') {
            if (!defined('FLUENTMAIL_GMAIL_CLIENT_ID') || !FLUENTMAIL_GMAIL_CLIENT_ID) {
                $errors['client_id']['required'] = __('Please define FLUENTMAIL_GMAIL_CLIENT_ID in wp-config.php file.', 'fluent-smtp');
            } else {
                $clientId = FLUENTMAIL_GMAIL_CLIENT_ID;
            }

            if (!defined('FLUENTMAIL_GMAIL_CLIENT_SECRET') || !FLUENTMAIL_GMAIL_CLIENT_SECRET) {
                $errors['client_secret']['required'] = __('Please define FLUENTMAIL_GMAIL_CLIENT_SECRET in wp-config.php file.', 'fluent-smtp');
            } else {
                $clientSecret = FLUENTMAIL_GMAIL_CLIENT_SECRET;
            }
        }

        if ($errors) {
            $this->throwValidationException($errors);
        }

        $accessToken = Arr::get($connection, 'access_token');
        $authToken = Arr::get($connection, 'auth_token');

        if (!$accessToken && $authToken) {
            // this is new, We have to generate the tokens
            $body = [
                'code'          => $authToken,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => apply_filters('fluentsmtp_gapi_callback', 'https://fluentsmtp.com/gapi/'), // 'urn:ietf:wg:oauth:2.0:oob'
                'client_id'     => $clientId,
                'client_secret' => $clientSecret
            ];
            $tokens = $this->makeRequest('https://accounts.google.com/o/oauth2/token', $body, 'POST');
            if (is_wp_error($tokens)) {
                $errors['auth_token']['required'] = $tokens->get_error_message();
            } else {
                add_filter('fluentmail_saving_connection_data', function ($con, $provider) use ($connection, $tokens) {

                    if ($provider != 'gmail') {
                        return $con;
                    }

                    if (Arr::get($con, 'connection.sender_email') != $connection['sender_email']) {
                        return $con;
                    }

                    $con['connection']['refresh_token'] = $tokens['refresh_token'];
                    $con['connection']['access_token'] = $tokens['access_token'];
                    $con['connection']['auth_token'] = '';
                    $con['connection']['expire_stamp'] = time() + $tokens['expires_in'];
                    $con['connection']['expires_in'] = $tokens['expires_in'];
                    $con['connection']['version'] = 2;
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

    private function makeRequest($url, $bodyArgs, $type = 'GET', $headers = false)
    {
        if (!$headers) {
            $headers = array(
                'Content-Type'              => 'application/http',
                'Content-Transfer-Encoding' => 'binary',
                'MIME-Version'              => '1.0',
            );
        }

        $args = [
            'headers' => $headers
        ];
        if ($bodyArgs) {
            $args['body'] = json_encode($bodyArgs);
        }


        $args['method'] = $type;
        $request = wp_remote_request($url, $args);

        if (is_wp_error($request)) {
            $message = $request->get_error_message();
            return new \WP_Error(423, $message);
        }

        $body = json_decode(wp_remote_retrieve_body($request), true);

        if (!empty($body['error'])) {
            $error = 'Unknown Error';
            if (isset($body['error_description'])) {
                $error = $body['error_description'];
            } else if (!empty($body['error']['message'])) {
                $error = $body['error']['message'];
            }
            return new \WP_Error(423, $error);
        }

        return $body;
    }

    private function saveNewTokens($existingData, $tokens)
    {
        if (empty($tokens['access_token']) || empty($tokens['refresh_token'])) {
            return false;
        }

        $senderEmail = $existingData['sender_email'];

        $existingData['access_token'] = $tokens['access_token'];
        $existingData['refresh_token'] = $tokens['refresh_token'];
        $existingData['expire_stamp'] = $tokens['expires_in'] + time();
        $existingData['expires_in'] = $tokens['expires_in'];

        (new Settings())->updateConnection($senderEmail, $existingData);
        fluentMailGetProvider($senderEmail, true); // we are clearing the static cache here
        return true;
    }

    private function getApiClient($data)
    {
        $senderEmail = $data['sender_email'];

        static $cachedServices = [];
        if (isset($cachedServices[$senderEmail])) {
            return $cachedServices[$senderEmail];
        }

        $client = new \Google_Client();
        $client->setClientId($data['client_id']);
        $client->setClientSecret($data['client_secret']);
        $client->addScope("https://www.googleapis.com/auth/gmail.compose");
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        $tokens = [
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in'    => $data['expire_stamp'] - time()
        ];

        $client->setAccessToken($tokens);

        // check if expired or will be expired in 120 seconds
        if (($data['expire_stamp'] - 120) < time()) {
            $newTokens = $client->refreshToken($data['refresh_token']);
            $this->saveNewTokens($data, $newTokens);
            $client->setAccessToken($newTokens);
        }

        $cachedServices[$senderEmail] = $client;

        return $cachedServices[$senderEmail];
    }

    public function getConnectionInfo($connection)
    {
        if (Arr::get($connection, 'key_store') == 'wp_config') {
            $connection['client_id'] = defined('FLUENTMAIL_GMAIL_CLIENT_ID') ? FLUENTMAIL_GMAIL_CLIENT_ID : '';
            $connection['client_secret'] = defined('FLUENTMAIL_GMAIL_CLIENT_SECRET') ? FLUENTMAIL_GMAIL_CLIENT_SECRET : '';
        }

        if (!class_exists('\Google_Client')) {
            require_once FLUENTMAIL_PLUGIN_PATH . 'includes/libs/google-api-client/vendor/autoload.php';
        }

        $this->getApiClient($connection);

        $info = fluentMailgetConnection($connection['sender_email']);

        $connection = $info->getSetting();

        $extraRow = [
            'title'   => __('Token Validity', 'fluent-smtp'),
            'content' => 'Valid (' . (int)(($connection['expire_stamp'] - time()) / 60) . 'm)'
        ];

        if (($connection['expire_stamp']) < time()) {
            $extraRow['content'] = 'Invalid. Please re-authenticate';
        }

        $connection['extra_rows'] = [$extraRow];

        return (string)fluentMail('view')->make('admin.general_connection_info', [
            'connection' => $connection
        ]);
    }
}
