<?php

namespace FluentMail\App\Services\Mailer\Providers\Kirimemail;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;


    protected $url  = null;

    public function send()
    {
        if ($this->preSend()) {
            $this->setUrl();
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(423, 'Something went wrong!', []) );
    }

    protected function setUrl()
    {
        return $this->url = 'https://appstaging.kirim.email/api/v3/transactional/messages';
    }

    public function postSend()
    {
        $body = [
            'from'           => $this->getFrom(),
            'subject'        => $this->getSubject(),
            'html'           => $this->getBody(),
            'h:X-Mailer'     => 'FluentMail - Kirim Email',
            'h:Content-Type' => $this->getHeader('content-type')
        ];

        if ($replyTo = $this->getReplyTo()) {
            $body['h:Reply-To'] = $replyTo;
        }

        $recipients = [
            'to'  => $this->getTo(),
            'cc'  => $this->getCarbonCopy(),
            'bcc' => $this->getBlindCarbonCopy()
        ];

        if ($recipients = array_filter($recipients)) {
            $body = array_merge($body, $recipients);
        }

        $params = [
            'body'    => $body,
            'headers' => $this->getRequestHeaders()
        ];

        $params = array_merge($params, $this->getDefaultParams());

        if (!empty($this->attributes['attachments'])) {
            $params = $this->getAttachments($params);
        }

        $response = wp_safe_remote_post($this->url, $params);
        
        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);
            $responseCode = wp_remote_retrieve_response_code($response);

            $isOKCode = $responseCode;
            
            if($isOKCode == 200 ) {
                $responseBody = \json_decode($responseBody, true);
                $returnResponse = [
                    'error' => Arr::get($responseBody,'error'),
                    'status'=>Arr::get($responseBody,'status'),
                    'message' => Arr::get($responseBody, 'message')
                ];
            } else {
                $responseBody = \json_decode($responseBody, true);
                $returnResponse = new \WP_Error($responseCode, 'Kirim Email API Error : '. Arr::get($responseBody, 'message'));
            }
        }

        return $this->handleResponse($returnResponse);
    }

    public function setSettings($settings)
    {
        if ($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_KIRIMEMAIL_API_KEY') ? FLUENTMAIL_KIRIMEMAIL_API_KEY : '';
            $settings['domain_name'] = defined('FLUENTMAIL_KIRIMEMAIL_DOMAIN') ? FLUENTMAIL_KIRIMEMAIL_DOMAIN : '';
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
        $domain = $this->getSetting('domain_name');
        return [
            'Authorization' => 'Basic ' . base64_encode('api:' . $apiKey),
            'domain'=>$domain
        ];
    }
}
