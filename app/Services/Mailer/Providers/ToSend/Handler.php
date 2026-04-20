<?php

namespace FluentMail\App\Services\Mailer\Providers\ToSend;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $baseUrl = 'https://api.tosend.com/v2/';

    private static $curlHandle = null;

    public function send()
    {
        if ($this->preSend() && $this->phpMailer->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong!', 'fluent-smtp'), []));
    }

    public function postSend()
    {
        $body = [
            'from'    => $this->getFrom(),
            'to'      => $this->getTo(),
            'subject' => $this->getSubject(),
            'api_key' => $this->getSetting('api_key')
        ];


        if ($replyTo = $this->getReplyTo()) {
            $body['reply_to'] = $replyTo;
        }

        if ($bcc = $this->getBlindCarbonCopy()) {
            $body['bcc'] = $bcc;
        }

        if ($cc = $this->getCarbonCopy()) {
            $body['cc'] = $cc;
        }

        $contentType = $this->getHeader('content-type');

        if ($contentType == 'text/html') {
            $body['html'] = $this->getParam('message');
        } else if ($contentType == 'multipart/alternative') {
            $body['html'] = $this->getParam('message');
            $body['text'] = $this->phpMailer->AltBody;
        } else {
            $body['text'] = $this->getParam('message');
        }

        if (!empty($this->getParam('attachments'))) {
            $body['attachments'] = $this->getAttachments();
        }

        // Add any custom headers
        $customHeaders = $this->phpMailer->getCustomHeaders();
        if (!empty($customHeaders)) {
            foreach ($customHeaders as $header) {
                $body['headers'][] = [
                    $header[0] => $header[1]
                ];
            }
        }

        $response = $this->sendViaCurl($this->baseUrl . 'emails', json_encode($body));

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseCode = (int)$response['code'];
            $isOKCode = $responseCode == $this->emailSentCode;
            $responseBody = \json_decode($response['body'], true);
            $messageId = Arr::get($responseBody, 'message_id');

            if ($isOKCode && $messageId) {
                $returnResponse = [
                    'id' => $messageId
                ];
            } else {
                $returnResponse = new \WP_Error($responseCode, Arr::get($responseBody, 'message', 'Unknown Error'), $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function getFrom()
    {
        $fromEmail = $this->phpMailer->From;
        if ($this->isForcedEmail() && !fluentMailIsListedSenderEmail($fromEmail)) {
            $fromEmail = $this->getSetting('sender_email');
        }

        $fromName = '';
        if (isset($this->phpMailer->FromName)) {
            $fromName = $this->phpMailer->FromName;
            if ($this->getSetting('force_from_name') == 'yes' &&
                $customFrom = $this->getSetting('sender_name')
            ) {
                $fromName = $customFrom;
            }
        }

        return [
            'name'  => $fromName,
            'email' => $fromEmail
        ];
    }

    public function setSettings($settings)
    {
        if ($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_TOSEND_API_KEY') ? FLUENTMAIL_TOSEND_API_KEY : '';
        }

        $this->settings = $settings;
        return $this;
    }

    protected function getReplyTo()
    {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            $replyTo = reset($replyTo);
            if (!filter_var($replyTo['email'], FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            return array_filter([
                'name'  => Arr::get($replyTo, 'name', ''),
                'email' => $replyTo['email']
            ]);
        }

        return '';
    }

    protected function getTo()
    {
        return $this->getRecipients($this->getParam('to'));
    }

    protected function getCarbonCopy()
    {
        return $this->getRecipients($this->getParam('headers.cc'));
    }

    protected function getBlindCarbonCopy()
    {
        return $this->getRecipients($this->getParam('headers.bcc'));
    }

    protected function getRecipients($recipients)
    {
        if (!$recipients) {
            return [];
        }

        $emails = array_map(function ($recipient) {
            $email = Arr::get($recipient, 'email');
            // validate the email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            return [
                'name'  => Arr::get($recipient, 'name', ''),
                'email' => Arr::get($recipient, 'email', '')
            ];
        }, $recipients);

        return array_values(array_filter($emails));
    }

    protected function getAttachments()
    {
        $attachments = [];

        foreach ($this->getParam('attachments') as $attachment) {
            $file = false;

            try {
                if (is_file($attachment[0]) && is_readable($attachment[0])) {
                    $fileName = basename($attachment[0]);
                    $file = file_get_contents($attachment[0]);
                    $mimeType = mime_content_type($attachment[0]);
                    $filetype = str_replace(';', '', trim($mimeType));
                }
            } catch (\Exception $e) {
                $file = false;
            }

            if ($file === false) {
                continue;
            }

            $chunkSize = 76;
            $file = base64_encode($file);
            $file = trim(chunk_split($file, $chunkSize, "\n"));

            $attachments[] = [
                'type'    => $filetype,
                'name'    => $fileName,
                'content' => $file
            ];
        }

        return $attachments;
    }

    protected function getRequestHeaders()
    {
        return [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json'
        ];
    }

    private function sendViaCurl($url, $jsonBody)
    {
        if (!function_exists('curl_init')) {
            $response = wp_remote_post($url, [
                'headers'   => $this->getRequestHeaders(),
                'body'      => $jsonBody,
                'sslverify' => false,
                'timeout'   => 30,
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            return [
                'code' => wp_remote_retrieve_response_code($response),
                'body' => wp_remote_retrieve_body($response),
            ];
        }

        if (self::$curlHandle === null) {
            self::$curlHandle = curl_init();
            curl_setopt(self::$curlHandle, CURLOPT_POST, true);
            curl_setopt(self::$curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(self::$curlHandle, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt(self::$curlHandle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt(self::$curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt(self::$curlHandle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt(self::$curlHandle, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt(self::$curlHandle, CURLOPT_TIMEOUT, 30);
            curl_setopt(self::$curlHandle, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json',
                'Connection: keep-alive',
            ]);
        }

        curl_setopt(self::$curlHandle, CURLOPT_URL, $url);
        curl_setopt(self::$curlHandle, CURLOPT_POSTFIELDS, $jsonBody);

        $body = curl_exec(self::$curlHandle);

        if ($body === false) {
            $errno = curl_errno(self::$curlHandle);
            $error = curl_error(self::$curlHandle);

            curl_close(self::$curlHandle);
            self::$curlHandle = null;

            return new \WP_Error('curl_' . $errno, $error ?: 'cURL request failed', [$error]);
        }

        return [
            'code' => curl_getinfo(self::$curlHandle, CURLINFO_RESPONSE_CODE),
            'body' => $body,
        ];
    }

    private function filterConnectionVars($connection)
    {
        if ($connection['key_store'] == 'wp_config') {
            $connection['api_key'] = defined('FLUENTMAIL_TOSEND_API_KEY') ? FLUENTMAIL_TOSEND_API_KEY : '';
        }

        return $connection;
    }


    public function getConnectionInfo($connection)
    {
        $connection = $this->filterConnectionVars($connection);
        $stats = $this->getAccountInfo($connection['api_key']);
        $error = '';

        if (is_wp_error($stats)) {
            $error = $stats->get_error_message();
            $stats = [];
        }


        if (empty($stats['account'])) {
            $info = (string)fluentMail('view')->make('admin.tosend_mailer_connection_info', [
                'connection'    => $connection,
                'valid_senders' => [$connection['sender_email']],
                'stats'         => $stats['account'] ?? [],
                'error'         => $error
            ]);

            return [
                'info' => $info
            ];
        }

        $validSenders = $this->getSendersFromMappings($connection);

        $connection['valid_senders'] = $validSenders['all_senders'];

        $info = (string)fluentMail('view')->make('admin.tosend_mailer_connection_info', [
            'connection' => $connection,
            'stats'      => $stats['account'] ?? [],
            'error'      => $error
        ]);

        $hasMultiDomain = $stats['verified_domains'] ? count($validSenders['verified_senders']) > 1 : false;

        return [
            'info'                 => $info,
            'verificationSettings' => [
                'connection_name'       => 'toSend',
                'all_senders'           => $validSenders['all_senders'],
                'verified_senders'      => $validSenders['verified_senders'],
                'verified_domain'       => $validSenders['verified_domain'],
                'supports_multi_domain' => $hasMultiDomain,
                'api_info'              => $stats,
                'email_help_message'    => $hasMultiDomain ? __('Make sure to verify your sender emails or domain in toSend dashboard and available in the provided API Key.', 'fluent-smtp') : ''
            ]
        ];
    }

    public function getValidSenders($connection)
    {
        $senders = [$connection['sender_email']];

        $additional = array_filter((array) Arr::get($connection, 'additional_senders', []));
        foreach ($additional as $extra) {
            if (is_email($extra)) {
                $senders[] = $extra;
            }
        }

        return array_values(array_unique(array_filter($senders)));
    }

    public function addNewSenderEmail($connection, $email)
    {

        $connection = $this->filterConnectionVars($connection);
        $stats = $this->getAccountInfo($connection['api_key']);
        if (is_wp_error($stats)) {
            return new \WP_Error(422, __('Unable to verify the connection details. Please check the API Key.', 'fluent-smtp'));
        }
        $verifiedDomains = Arr::get($stats, 'verified_domains', []);
        $emailDomain = explode('@', $email);
        $emailDomain = $emailDomain[1];

        if (!in_array($emailDomain, $verifiedDomains)) {
            return new \WP_Error(422, __('Invalid email address! Please use an email with verified domain.', 'fluent-smtp'));
        }

        $settings = fluentMailGetSettings();
        $mappings = Arr::get($settings, 'mappings', []);

        if (isset($mappings[$email])) {
            return new \WP_Error(422, __('Email address already exists with another connection. Please choose a different email.', 'fluent-smtp'));
        }

        $settings = get_option('fluentmail-settings');

        $settings['mappings'][$email] = md5($connection['sender_email']);

        update_option('fluentmail-settings', $settings);

        return true;
    }

    public function removeSenderEmail($connection, $email)
    {
        $connection = $this->filterConnectionVars($connection);

        $settings = fluentMailGetSettings();
        $mappings = Arr::get($settings, 'mappings', []);

        if (!isset($mappings[$email])) {
            return new \WP_Error(422, __('Email does not exists. Please try again.', 'fluent-smtp'));
        }

        if ($email == $connection['sender_email']) {
            return new \WP_Error(422, __('You can not remove the primary sender email of this connection.', 'fluent-smtp'));
        }

        // check if the it's the same email or not
        if ($mappings[$email] != md5($connection['sender_email'])) {
            return new \WP_Error(422, __('Email does not exists. Please try again.', 'fluent-smtp'));
        }

        $settings = get_option('fluentmail-settings');

        unset($settings['mappings'][$email]);

        update_option('fluentmail-settings', $settings);

        return true;
    }

    private function getSendersFromMappings($connection)
    {
        $validSenders = [
            'emails' => [$connection['sender_email']]
        ];
        $verifiedDomain = explode('@', $connection['sender_email'])[1] ?? '';

        if ($verifiedDomain) {
            $settings = fluentMailGetSettings();
            $mappings = Arr::get($settings, 'mappings', []);

            $mapKey = md5($connection['sender_email']);
            $mapSenders = array_filter($mappings, function ($key) use ($mapKey) {
                return $key == $mapKey;
            });

            $mapSenders[$connection['sender_email']] = true;

            foreach ($validSenders['emails'] as $email) {
                $mapSenders[$email] = $email;
            }

            $mapSenders = array_keys($mapSenders);

        } else {
            $mapSenders = $validSenders['emails'];
        }

        return [
            'all_senders'      => $mapSenders,
            'verified_senders' => $validSenders['emails'],
            'verified_domain'  => $verifiedDomain
        ];
    }

}
