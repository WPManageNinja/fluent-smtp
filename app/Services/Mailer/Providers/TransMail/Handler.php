<?php

namespace FluentMail\App\Services\Mailer\Providers\TransMail;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $url = null;

    public function send()
    {
        if ($this->preSend()) {
            $this->setUrl();
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong!', 'fluent-smtp'), []));
    }

    protected function setUrl()
    {
        return $this->url = 'https://transmail.zoho.' . $this->getSetting('domain_name') . '/v1.1/email';
    }

    public function postSend()
    {
        $body = [
            'bounce_address' => $this->getSetting('bounce_address'),
            'from'           => json_encode([
                'name'    => $this->phpMailer->FromName,
                'address' => $this->phpMailer->From,
            ]),
            'to'             => json_encode([
                [
                    'email_address' => [
                        'address' => '',
                        'name'    => ''
                    ]
                ]
            ]),
            'cc'             => json_encode([
                [
                    'email_address' => [
                        'address' => '',
                        'name'    => ''
                    ]
                ]
            ]),
            'bcc'            => json_encode([
                [
                    'email_address' => [
                        'address' => '',
                        'name'    => ''
                    ]
                ]
            ]),
            'reply_to'       => json_encode([
                'address' => '',
                'name'    => ''
            ]),
            'subject'        => $this->getSubject()
        ];

        if ($this->phpMailer->ContentType == 'text/html') {
            $body['htmlbody'] = $this->getBody();
        } else if ($this->phpMailer->ContentType == 'multipart/alternative') {
            $body['htmlbody'] = $this->getBody();
            $body['textbody'] = $this->getParam('alt_body');
        } else {
            $body['textbody'] = $this->getBody();
        }

        $headers1 = array(
            'Authorization' => 'Zoho-enczapikey ' . $this->getSetting('token'),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json'
        );

        $data_string = json_encode($body);

        $args = array(
            'body'    => $data_string,
            'headers' => $headers1,
            'method'  => 'POST'
        );

        $response = wp_safe_remote_post($this->url, $args);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);
            $responseCode = wp_remote_retrieve_response_code($response);

            $isOKCode = $responseCode == $this->emailSentCode;

            if ($isOKCode) {
                $responseBody = \json_decode($responseBody, true);
            }

            if ($isOKCode && isset($responseBody['id'])) {
                $returnResponse = [
                    'id'      => Arr::get($responseBody, 'id'),
                    'message' => Arr::get($responseBody, 'message')
                ];
            } else {
                $returnResponse = new \WP_Error($responseCode, __('Mailgun API Error', 'fluent-smtp'), $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if ($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_MAILGUN_API_KEY') ? FLUENTMAIL_MAILGUN_API_KEY : '';
            $settings['domain_name'] = defined('FLUENTMAIL_MAILGUN_DOMAIN') ? FLUENTMAIL_MAILGUN_DOMAIN : '';
        }
        $this->settings = $settings;

        return $this;
    }

    protected function getFrom()
    {
        return $this->getParam('from');
    }

    protected function getReplyTo()
    {
        return $this->getRecipients(
            $this->getParam('headers.reply-to')
        );
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
        $array = array_map(function ($recipient) {
            return isset($recipient['name'])
                ? $recipient['name'] . ' <' . $recipient['email'] . '>'
                : $recipient['email'];
        }, $recipients);

        return implode(', ', $array);
    }

    protected function getBody()
    {
        return $this->getParam('message');
    }

    protected function getAttachments($params)
    {
        $data = [];
        $payload = '';
        $attachments = $this->attributes['attachments'];

        foreach ($attachments as $attachment) {
            $file = false;

            try {
                if (is_file($attachment[0]) && is_readable($attachment[0])) {
                    $fileName = basename($attachment[0]);
                    $file = file_get_contents($attachment[0]);
                }
            } catch (\Exception $e) {
                $file = false;
            }

            if ($file === false) {
                continue;
            }

            $data[] = [
                'content' => $file,
                'name'    => $fileName,
            ];
        }

        if (!empty($data)) {
            $boundary = hash('sha256', uniqid('', true));

            foreach ($params['body'] as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $child_key => $child_value) {
                        $payload .= '--' . $boundary;
                        $payload .= "\r\n";
                        $payload .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
                        $payload .= $child_value;
                        $payload .= "\r\n";
                    }
                } else {
                    $payload .= '--' . $boundary;
                    $payload .= "\r\n";
                    $payload .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
                    $payload .= $value;
                    $payload .= "\r\n";
                }
            }

            foreach ($data as $key => $attachment) {
                $payload .= '--' . $boundary;
                $payload .= "\r\n";
                $payload .= 'Content-Disposition: form-data; name="attachment[' . $key . ']"; filename="' . $attachment['name'] . '"' . "\r\n\r\n";
                $payload .= $attachment['content'];
                $payload .= "\r\n";
            }

            $payload .= '--' . $boundary . '--';

            $params['body'] = $payload;

            $params['headers']['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

            $this->attributes['headers']['content-type'] = 'multipart/form-data';
        }

        return $params;
    }

    protected function getRequestHeaders()
    {
        $apiKey = $this->getSetting('api_key');

        return [
            'Authorization' => 'Basic ' . base64_encode('api:' . $apiKey)
        ];
    }
}
