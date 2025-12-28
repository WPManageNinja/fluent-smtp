<?php

namespace FluentMail\App\Services\Mailer\Providers\ToSend;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $baseUrl = 'https://api.tosend.com/v2/';

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

        $args = array(
            'headers'   => $this->getRequestHeaders(),
            'body'      => json_encode($body),
            'sslverify' => false
        );

        $response = wp_remote_post($this->baseUrl . 'emails', $args);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);

            $responseCode = wp_remote_retrieve_response_code($response);
            $isOKCode = $responseCode == $this->emailSentCode;
            $responseBody = \json_decode($responseBody, true);
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

            $name = Arr::get($replyTo, 'name', '');

            if ($name) {
                return $name . ' <' . $replyTo['email'] . '>';
            }

            return $replyTo['email'];
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
                'connection_name'       => 'ToSend',
                'all_senders'           => $validSenders['all_senders'],
                'verified_senders'      => $validSenders['verified_senders'],
                'verified_domain'       => $validSenders['verified_domain'],
                'supports_multi_domain' => $hasMultiDomain,
                'api_info'   => $stats,
                'email_help_message'    => $hasMultiDomain ? __('Make sure to verify your sender emails or domain in toSend dashboard and available in the provided API Key.', 'fluent-smtp') : ''
            ]
        ];
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
