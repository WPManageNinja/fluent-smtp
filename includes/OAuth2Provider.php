<?php

namespace FluentMail\Includes;

class OAuth2Provider
{
    private $options;

    private $accessTokenMethod = 'POST';

    const METHOD_GET = 'GET';

    public function __construct($options = [])
    {
        $this->assertRequiredOptions($options);
        $this->options = $options;
    }

    public function getAuthorizationUrl($options = [])
    {
        $base   = $this->options['urlAuthorize'];

        $params = $this->getAuthorizationParameters($options);
        $query  = $this->getAuthorizationQuery($params);

        return $this->appendQuery($base, $query);
    }

    private function getAuthorizationParameters($options)
    {
        if (empty($options['state'])) {
            $options['state'] = $this->getRandomState();
        }

        if (empty($options['scope'])) {
            $options['scope'] = $this->options['scopes'];
        }

        $options += [
            'response_type'   => 'code',
            'approval_prompt' => 'auto'
        ];

        if (is_array($options['scope'])) {
            $separator = ',';
            $options['scope'] = implode($separator, $options['scope']);
        }

        // Store the state as it may need to be accessed later on.
        $this->options['state'] = $options['state'];

        // Business code layer might set a different redirect_uri parameter
        // depending on the context, leave it as-is
        if (!isset($options['redirect_uri'])) {
            $options['redirect_uri'] = $this->options['redirectUri'];
        }

        $options['client_id'] = $this->options['clientId'];

        return $options;
    }


    /**
     * Appends a query string to a URL.
     *
     * @param  string $url The URL to append the query to
     * @param  string $query The HTTP query string
     * @return string The resulting URL
     */
    protected function appendQuery($url, $query)
    {
        $query = trim($query, '?&');

        if ($query) {
            $glue = strstr($url, '?') === false ? '?' : '&';
            return $url . $glue . $query;
        }

        return $url;
    }

    /**
     * Builds the authorization URL's query string.
     *
     * @param  array $params Query parameters
     * @return string Query string
     */
    protected function getAuthorizationQuery(array $params)
    {
        return $this->buildQueryString($params);
    }

    /**
     * Build a query string from an array.
     *
     * @param array $params
     *
     * @return string
     */
    protected function buildQueryString(array $params)
    {
        return http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * Verifies that all required options have been passed.
     *
     * @param  array $options
     * @return void
     * @throws \InvalidArgumentException
     */
    private function assertRequiredOptions(array $options)
    {
        $missing = array_diff_key(array_flip($this->getRequiredOptions()), $options);

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                'Required options not defined: ' . implode(', ', array_keys($missing)) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    /**
     * Returns all options that are required.
     *
     * @return array
     */
    protected function getRequiredOptions()
    {
        return [
            'urlAuthorize',
            'urlAccessToken',
            'urlResourceOwnerDetails',
        ];
    }


    /**
     * Returns a new random string to use as the state parameter in an
     * authorization flow.
     *
     * @param  int $length Length of the random string to be generated.
     * @return string
     */
    protected function getRandomState($length = 32)
    {
        // Converting bytes to hex will always double length. Hence, we can reduce
        // the amount of bytes by half to produce the correct length.
        $state = bin2hex(random_bytes($length / 2));

        update_option('_fluentmail_last_generated_state', $state);

        return $state;
    }



    /**
     * Requests an access token using a specified grant and option set.
     *
     * @param  mixed $grant
     * @param  array $options
     * @throws \Exception
     * @return array tokens
     */
    public function getAccessToken($grant, array $options = [])
    {
        $params = [
            'client_id'     => $this->options['clientId'],
            'client_secret' => $this->options['clientSecret'],
            'redirect_uri'  => $this->options['redirectUri'],
            'grant_type' => $grant,
        ];

        $params += $options;

        $requestData = $this->getAccessTokenRequestDetails($params);

        $response = wp_remote_request($requestData['url'], $requestData['params']);

        if (is_wp_error($response)) {
            throw new \Exception(
                wp_kses_post($response->get_error_message())
            );
        }

        $responseBody = wp_remote_retrieve_body($response);

        if (false === is_array($response)) {
            throw new \Exception(
                'Invalid response received from Authorization Server. Expected JSON.'
            );
        }

        if(empty(['access_token'])) {
            throw new \Exception(
                'Invalid response received from Authorization Server.'
            );
        }

        return \json_decode($responseBody, true);
    }


    /**
     * Returns a prepared request for requesting an access token.
     *
     * @param array $params Query string parameters
     * @return array $requestDetails
     */
    protected function getAccessTokenRequestDetails($params)
    {
        $method  = $this->accessTokenMethod;
        $url     = $this->getAccessTokenUrl($params);
        $options = $this->buildQueryString($params);

        return [
            'url' => $url,
            'params' => [
                'method' => $method,
                'body' => $options,
                'headers' => [
                    'content-type' => 'application/x-www-form-urlencoded'
                ]
            ]
        ];
    }

    /**
     * Returns the full URL to use when requesting an access token.
     *
     * @param array $params Query parameters
     * @return string
     */
    protected function getAccessTokenUrl($params)
    {
        $url = $this->options['urlAccessToken'];

        if ($this->accessTokenMethod === self::METHOD_GET) {
            $query = $this->getAccessTokenQuery($params);
            return $this->appendQuery($url, $query);
        }

        return $url;
    }

    /**
     * Builds the access token URL's query string.
     *
     * @param  array $params Query parameters
     * @return string Query string
     */
    protected function getAccessTokenQuery(array $params)
    {
        return $this->buildQueryString($params);
    }
}
