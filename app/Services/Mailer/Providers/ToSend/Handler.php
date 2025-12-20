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
            $body['Cc'] = $cc;
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

            if ($isOKCode) {
                $returnResponse = [
                    'id' => Arr::get($responseBody, 'message_id')
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
            if ( $this->getSetting('force_from_name') == 'yes' &&
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
        $tos = $this->getRecipients($this->getParam('to'));

        return $tos[0];
    }

    protected function getCarbonCopy()
    {
        $ccEmails = $this->getRecipients($this->getParam('headers.cc'));

        $to = $this->getParam('to');

        if (count($to) > 1) {
            // get the all other emails except the first one
            $toEmails = array_map(function ($recipient) {
                return [
                    'name'  => Arr::get($recipient, 'name', ''),
                    'email' => Arr::get($recipient, 'email')
                ];
            }, array_slice($to, 1));
            // merge cc and to emails
            return array_merge($ccEmails, $toEmails);
        }

        return $ccEmails;
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

        $info = (string)fluentMail('view')->make('admin.tosend_mailer_connection_info', [
            'connection'    => $connection,
            'valid_senders' => [$connection['sender_email']],
            'stats'         => $stats['account'],
            'error'         => $error
        ]);

        return [
            'info' => $info,
//            'verificationSettings' => [
//                'connection_name'  => 'FluentMailer',
//                'verified_senders' => [$connection['sender_email']],
//                'verified_domain'  => implode(', ', $stats['verified_domains']),
//            ]
        ];
    }

}
