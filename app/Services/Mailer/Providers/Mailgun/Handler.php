<?php

namespace FluentMail\App\Services\Mailer\Providers\Mailgun;

use WP_Error as WPError;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\Mailgun\ValidatorTrait;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;
    
    protected $url = null;

    const API_BASE_US = 'https://api.mailgun.net/v3/';
    
    const API_BASE_EU = 'https://api.eu.mailgun.net/v3/';

    public function send()
    {
        if ($this->preSend()) {
            $this->setUrl();
            return $this->postSend();
        }

        $this->handleFailure(new \Exception('Something went wrong!', 0));
    }

    protected function setUrl()
    {
        $url = $this->getSetting('region') == 'eu' ? self::API_BASE_EU : self::API_BASE_US;

        $url .= sanitize_text_field($this->getSetting('domain_name') . '/messages');

        return $this->url = $url;
    }

    public function postSend()
    {
        $body = [
            'from' => $this->getFrom(),
            'subject' => $this->getSubject(),
            'html' => $this->getBody(),
            'h:X-Mailer' => 'FluentMail - Mailgun',
            'h:Content-Type' => $this->getHeader('content-type')
        ];

        if ($replyTo = $this->getReplyTo()) {
            $body['h:Reply-To'] = $replyTo;
        }

        $recipients = [
            'to' => $this->getTo(),
            'cc' => $this->getCarbonCopy(),
            'bcc' => $this->getBlindCarbonCopy()
        ];

        if ($recipients = array_filter($recipients)) {
            $body = array_merge($body, $recipients);
        }

        $params = [
            'body' => $body,
            'headers' => $this->getRequestHeaders()
        ];

        $params = array_merge($params, $this->getDefaultParams());
        
        if (!empty($this->attributes['attachments'])) {
            $params = $this->getAttachments($params);
        }
        
        $this->response = wp_safe_remote_post($this->url, $params);

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if($settings['key_store'] == 'wp_config') {
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
        $array = array_map(function($recipient) {
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
            }
            catch (\Exception $e) {
                $file = false;
            }

            if ($file === false) {
                continue;
            }

            $data[] = [
                'content' => $file,
                'name' => $fileName,
            ];
        }

        if (!empty($data)) {
            $boundary = hash('sha256', uniqid('', true));

            foreach ($params['body'] as $key => $value ) {
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

    public function isEmailSent()
    {
        $isSent = wp_remote_retrieve_response_code($this->response) == $this->emailSentCode;

        if (
            $isSent &&
            isset($this->response['body']) &&
            !array_key_exists('id', (array) json_decode($this->response['body'], true))
        ) {
            return false;
        }

        return $isSent;
    }

    public function handleSuccess()
    {
        $response = (array) json_decode($this->response['body'], true);

        return $this->processResponse(['response' => $response], true);
    }

    public function handleFailure()
    {
        $response = $this->getResponseError();

        $this->processResponse(['response' => $response], false);

        $this->fireWPMailFailedAction($response);
    }

    protected function getResponseError()
    {
        $body = (array) json_decode($this->response['body'], true);

        if (json_last_error()) {
            $body = $this->response['body'];
        } else {
            $body = is_array($body) && isset($body['message']) ? $body['message'] : 'Unknown Error!';
        }

        return [
            'message' => $this->response['response']['message'],
            'code' => $this->response['response']['code'],
            'errors' => [$body]
        ];
    }
}
