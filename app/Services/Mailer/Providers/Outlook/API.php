<?php

namespace FluentMail\App\Services\Mailer\Providers\Outlook;

use FluentMail\Includes\Support\Arr;

class API
{
    private $clientId;
    private $clientSecret;

    public function __construct($clientId = '', $clientSecret = '')
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function getAuthUrl()
    {

        $fluentClient = new \FluentMail\Includes\OAuth2Provider($this->getConfig());

        return  $fluentClient->getAuthorizationUrl();

    }

    public function generateToken($authCode)
    {
        return $this->sendTokenRequest('authorization_code', [
            'code' => $authCode
        ]);
    }


    /**
     * @return mixed|string
     */
    public function sendTokenRequest($type, $params)
    {
        $fluentClient = new \FluentMail\Includes\OAuth2Provider($this->getConfig());
        try {
            $tokens = $fluentClient->getAccessToken($type, $params);
            return $tokens;
        } catch (\Exception $exception) {
            return new \WP_Error(423, $exception->getMessage());
        }
    }

    /**
     * @return array | \WP_Error
     */
    public function sendMime($mime, $accessToken)
    {
        $response = wp_remote_request('https://graph.microsoft.com/v1.0/me/sendMail', [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer '. $accessToken,
                'Content-Type' => 'text/plain'
            ],
            'body' => $mime
        ]);

        if(is_wp_error($response)) {
            return $response;
        }

        $responseCode = wp_remote_retrieve_response_code($response);

        if($responseCode >= 300) {
            $responseBody = json_decode(wp_remote_retrieve_body($response), true);
            if(!$error = Arr::get($responseBody, 'error.message')) {
                $error = 'Something with wrong with Outlook API. Please check your API Settings';
            }
            return new \WP_Error($responseCode, $error);
        }

        $header = wp_remote_retrieve_headers($response);

        return $header->getAll();
    }

    public function getRedirectUrl()
    {
        return rest_url('fluent-smtp/outlook_callback');
    }

    private function getConfig()
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'redirectUri' => $this->getRedirectUrl(),
            'urlAuthorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
            'scopes' => 'offline_access user.read Mail.Send'
        ];
    }

}