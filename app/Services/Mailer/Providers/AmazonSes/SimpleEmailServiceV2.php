<?php

namespace FluentMail\App\Services\Mailer\Providers\AmazonSes;

/**
 * SES V2 API Client
 *
 * Uses the SES V2 API with JSON requests/responses for improved functionality
 * including tenant support, configuration sets, and more.
 */
class SimpleEmailServiceV2
{
    /**
     * AWS Access Key
     * @var string
     */
    protected $accessKey;

    /**
     * AWS Secret Key
     * @var string
     */
    protected $secretKey;

    /**
     * AWS Region (e.g., 'us-east-1')
     * @var string
     */
    protected $region;

    /**
     * AWS Host
     * @var string
     */
    protected $host;

    /**
     * Verify SSL certificates
     * @var bool
     */
    protected $verifyPeer = true;

    /**
     * Last response from API
     * @var array|null
     */
    protected $lastResponse = null;

    /**
     * Last error message
     * @var string|null
     */
    protected $lastError = null;

    /**
     * Constructor
     *
     * @param string $accessKey AWS Access Key
     * @param string $secretKey AWS Secret Key
     * @param string $region AWS Region (e.g., 'us-east-1')
     * @param bool $verifyPeer Whether to verify SSL certificates
     */
    public function __construct($accessKey, $secretKey, $region, $verifyPeer = true)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region;
        $this->host = 'email.' . $region . '.amazonaws.com';
        $this->verifyPeer = $verifyPeer;
    }

    /**
     * Get the last response
     *
     * @return array|null
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Get the last error
     *
     * @return string|null
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Send an email using SES V2 API
     *
     * @param string $rawMessage Base64-encoded raw MIME message
     * @param string|null $tenantName Optional tenant name for isolation
     * @param string|null $configurationSetName Optional configuration set name
     * @return array Response with MessageId on success
     * @throws \Exception On API error
     */
    public function sendEmail($rawMessage, $tenantName = null, $configurationSetName = null)
    {
        $body = [
            'Content' => [
                'Raw' => [
                    'Data' => $rawMessage
                ]
            ]
        ];

        // Configuration Set is required when using a tenant
        if (!empty($configurationSetName)) {
            $body['ConfigurationSetName'] = $configurationSetName;
        }

        // Add tenant if specified (requires ConfigurationSetName)
        if (!empty($tenantName)) {
            if (empty($configurationSetName)) {
                throw new \Exception('ConfigurationSetName is required when using TenantName');
            }
            $body['TenantName'] = $tenantName;
        }

        $response = $this->request('POST', '/v2/email/outbound-emails', $body);

        if (!empty($response['error'])) {
            throw new \Exception($response['error']);
        }

        return $response;
    }

    /**
     * Get account details (useful for connection validation)
     *
     * @return array Account details
     * @throws \Exception On API error
     */
    public function getAccount()
    {
        $response = $this->request('GET', '/v2/email/account');

        if (!empty($response['error'])) {
            throw new \Exception($response['error']);
        }

        return $response;
    }

    /**
     * List email identities (verified domains and emails)
     *
     * @param int $pageSize Number of results per page
     * @param string|null $nextToken Pagination token
     * @return array List of identities
     * @throws \Exception On API error
     */
    public function listEmailIdentities($pageSize = 100, $nextToken = null)
    {
        $queryParams = ['PageSize' => $pageSize];
        if ($nextToken) {
            $queryParams['NextToken'] = $nextToken;
        }

        $path = '/v2/email/identities';
        if (!empty($queryParams)) {
            $path .= '?' . http_build_query($queryParams);
        }

        $response = $this->request('GET', $path);

        if (!empty($response['error'])) {
            throw new \Exception($response['error']);
        }

        return $response;
    }

    /**
     * Get configuration set details
     *
     * @param string $configurationSetName The configuration set name to look up
     * @return array Configuration set details
     * @throws \Exception On API error
     */
    public function getConfigurationSet($configurationSetName)
    {
        $path = '/v2/email/configuration-sets/' . rawurlencode($configurationSetName);

        $response = $this->request('GET', $path);

        if (!empty($response['error'])) {
            throw new \Exception($response['error']);
        }

        return $response;
    }

    /**
     * Get tenant details
     *
     * @param string $tenantName The tenant name to look up
     * @return array Tenant details
     * @throws \Exception On API error
     */
    public function getTenant($tenantName)
    {
        $body = [
            'TenantName' => $tenantName
        ];

        $response = $this->request('POST', '/v2/email/tenants/get', $body);

        if (!empty($response['error'])) {
            throw new \Exception($response['error']);
        }

        return $response;
    }

    /**
     * Make an authenticated request to the SES V2 API
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path API path (e.g., '/v2/email/account')
     * @param array|null $body Request body for POST requests
     * @return array Response data or error
     */
    protected function request($method, $path, $body = null)
    {
        $this->lastError = null;
        $this->lastResponse = null;

        $service = 'ses';
        $algorithm = 'AWS4-HMAC-SHA256';
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        // Parse path and query string
        $pathParts = explode('?', $path, 2);
        $canonicalUri = $pathParts[0];
        $queryString = isset($pathParts[1]) ? $pathParts[1] : '';

        // Sort query parameters for canonical request
        $canonicalQueryString = '';
        if (!empty($queryString)) {
            parse_str($queryString, $params);
            ksort($params);
            $canonicalQueryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        // Prepare body
        $bodyContent = '';
        if ($body !== null && $method !== 'GET') {
            $bodyContent = json_encode($body);
        }

        $payloadHash = hash('sha256', $bodyContent);

        // Build headers
        $headers = [
            'Host' => $this->host,
            'X-Amz-Date' => $timestamp,
            'X-Amz-Content-Sha256' => $payloadHash,
        ];

        if (!empty($bodyContent)) {
            $headers['Content-Type'] = 'application/json';
        }

        // Build canonical headers
        $canonicalHeaders = '';
        $signedHeaders = [];
        ksort($headers);
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
            $signedHeaders[] = strtolower($key);
        }
        $signedHeadersStr = implode(';', $signedHeaders);

        // Build canonical request
        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            $canonicalQueryString,
            $canonicalHeaders,
            $signedHeadersStr,
            $payloadHash
        ]);

        // Build string to sign
        $credentialScope = $date . '/' . $this->region . '/' . $service . '/aws4_request';
        $stringToSign = implode("\n", [
            $algorithm,
            $timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest)
        ]);

        // Calculate signature
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // Build authorization header
        $authorization = $algorithm . ' ' .
            'Credential=' . $this->accessKey . '/' . $credentialScope . ', ' .
            'SignedHeaders=' . $signedHeadersStr . ', ' .
            'Signature=' . $signature;

        $headers['Authorization'] = $authorization;

        // Build URL
        $url = 'https://' . $this->host . $path;

        // Execute request
        return $this->executeRequest($method, $url, $headers, $bodyContent);
    }

    /**
     * Execute the HTTP request
     *
     * @param string $method HTTP method
     * @param string $url Full URL
     * @param array $headers HTTP headers
     * @param string $body Request body
     * @return array Response data or error
     */
    protected function executeRequest($method, $url, $headers, $body)
    {
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = $key . ': ' . $value;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => $this->verifyPeer,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if (!empty($body) && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno) {
            $this->lastError = 'cURL error: ' . $curlError;
            return ['error' => $this->lastError];
        }

        // Parse JSON response
        $response = [];
        if (!empty($responseBody)) {
            $response = json_decode($responseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Response might be empty for some successful requests
                if ($httpCode >= 200 && $httpCode < 300) {
                    $response = ['success' => true, 'httpCode' => $httpCode];
                } else {
                    $this->lastError = 'Invalid JSON response: ' . $responseBody;
                    return ['error' => $this->lastError];
                }
            }
        }

        $this->lastResponse = $response;

        // Check for errors
        if ($httpCode >= 400) {
            $errorMessage = isset($response['message']) ? $response['message'] : 
                           (isset($response['Message']) ? $response['Message'] : 'Unknown error');
            $errorType = isset($response['__type']) ? $response['__type'] : 'Error';
            
            // Clean up error type (remove namespace prefix)
            if (strpos($errorType, '#') !== false) {
                $errorType = substr($errorType, strrpos($errorType, '#') + 1);
            }
            
            $this->lastError = $errorType . ': ' . $errorMessage;
            return ['error' => $this->lastError, 'httpCode' => $httpCode, 'response' => $response];
        }

        return $response;
    }

    /**
     * Get verified email identities (for backwards compatibility)
     *
     * @return array Array with 'Identities' key containing list of verified emails
     */
    public function listVerifiedEmailAddresses()
    {
        try {
            $identities = $this->listEmailIdentities();
            
            $verifiedEmails = [];
            if (isset($identities['EmailIdentities'])) {
                foreach ($identities['EmailIdentities'] as $identity) {
                    // Only include verified identities
                    if (isset($identity['SendingEnabled']) && $identity['SendingEnabled']) {
                        $verifiedEmails[] = $identity['IdentityName'];
                    }
                }
            }
            
            return ['Identities' => $verifiedEmails];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
