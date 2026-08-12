<?php

namespace FluentMail\App\Services\Mailer\Providers\Outlook;

use FluentMail\Includes\Support\Arr;

class API
{
    /**
     * Microsoft's multi-tenant authority, and what every connection used
     * before the tenant became configurable.
     */
    const DEFAULT_TENANT = 'common';

    /**
     * Authorities that are not a directory but a sign-in audience.
     *
     * `organizations` is the useful one for corporate Entra tenants that
     * forbid personal Microsoft accounts but do not want to pin a single
     * directory.
     */
    const AUDIENCE_TENANTS = ['common', 'organizations', 'consumers'];

    private $clientId;
    private $clientSecret;
    private $tenantId;

    public function __construct($clientId = '', $clientSecret = '', $tenantId = '')
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tenantId     = self::resolveTenant($tenantId);
    }

    /**
     * Whether a value may be used as the tenant segment of the authority URL.
     *
     * Microsoft accepts a directory GUID, a verified domain, or one of the
     * sign-in audiences. Everything else is refused, and deliberately so: this
     * value is interpolated into the URL that the client secret is POSTed to,
     * so a value carrying a scheme, host, credential or path separator could
     * hand those credentials to somebody else's server. Matching an allow-list
     * of shapes — rather than escaping a denied set — is what keeps that shut.
     *
     * @param string $tenant
     * @return bool
     */
    public static function isValidTenant($tenant)
    {
        $tenant = trim((string)$tenant);

        if ($tenant === '') {
            return true; // Optional: an empty value means the default authority.
        }

        if (in_array(strtolower($tenant), self::AUDIENCE_TENANTS, true)) {
            return true;
        }

        // Directory (tenant) ID, as Entra shows it on the app overview page.
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenant)) {
            return true;
        }

        // A verified domain, e.g. contoso.onmicrosoft.com. Must be dotted
        // labels of unreserved characters and nothing else, so it cannot carry
        // a port, userinfo, query or path.
        if (strlen($tenant) <= 253 && preg_match(
            '/^[A-Za-z0-9]([A-Za-z0-9\-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9\-]{0,61}[A-Za-z0-9])?)+$/',
            $tenant
        )) {
            return true;
        }

        return false;
    }

    /**
     * The tenant segment to build URLs from.
     *
     * Falls back to the default authority for anything unusable rather than
     * trusting what is stored. Validation on save is the first gate; this is
     * the second, so a row written before this existed — or by anything that
     * bypasses the settings screen — still cannot steer the token request.
     *
     * @param string $tenant
     * @return string
     */
    public static function resolveTenant($tenant)
    {
        $tenant = trim((string)$tenant);

        if ($tenant === '' || !self::isValidTenant($tenant)) {
            return self::DEFAULT_TENANT;
        }

        return $tenant;
    }

    public function getAuthUrl()
    {

        $fluentClient = new \FluentMail\Includes\OAuth2Provider($this->getConfig());

        return $fluentClient->getAuthorizationUrl();

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
        } catch (\Exception$exception) {
            return new \WP_Error(422, $exception->getMessage());
        }
    }

    /**
     * @return array | \WP_Error
     */
    public function sendMime($mime, $accessToken)
    {
        $response = wp_remote_request('https://graph.microsoft.com/v1.0/me/sendMail', [
            'method'  => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'text/plain'
            ],
            'body'    => $mime
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $responseCode = wp_remote_retrieve_response_code($response);

        if ($responseCode >= 300) {
            /*
             * Graph's own message first. The other candidate is the HTTP
             * reason phrase, which is set on essentially every failure and so
             * always won the old ordering - that is where the useless
             * "Unauthorized" in the logs came from, while the body sitting
             * right next to it said the access token had expired and the
             * account needed reconnecting.
             */
            $responseBody = json_decode(wp_remote_retrieve_body($response), true);

            $error = Arr::get($responseBody, 'error.message');

            if (!$error) {
                $error = Arr::get($response, 'response.message');
            }

            if (!$error) {
                $error = __('Something went wrong with the Outlook API. Please check your API Settings', 'fluent-smtp');
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
            'clientId'                => $this->clientId,
            'clientSecret'            => $this->clientSecret,
            'redirectUri'             => $this->getRedirectUrl(),
            'urlAuthorize'            => 'https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/authorize',
            'urlAccessToken'          => 'https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
            'scopes'                  => 'https://graph.microsoft.com/user.read https://graph.microsoft.com/mail.readwrite https://graph.microsoft.com/mail.send https://graph.microsoft.com/mail.send.shared offline_access'
        ];
    }

}
