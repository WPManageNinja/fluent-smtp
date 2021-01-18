<?php

namespace FluentMail\App\Services\Mailer\Providers\SparkPost;

use WP_Error as WPError;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\SparkPost\ValidatorTrait;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 202;

    protected $url = 'https://api.sparkpost.com/api/v1/transmissions';

    public function send()
    {   
        if ($this->preSend()) {
            return $this->postSend();
        }

        $this->handleFailure(new Exception('Something went wrong!', 0));
    }

    public function postSend()
    {
        $body = [
            'options' => [
                'sandbox' => defined('FLUENTMAIL_TEST_EMAIL')
            ],
            'content' => [
                'from' => $this->getFrom(),
                'subject' => $this->getSubject(),
                'html' => $this->phpMailer->Body,
                'text' => $this->phpMailer->AltBody,
                'headers' => []
            ],
            'recipients' => [
                [
                    'address' => [
                        'name' => $this->getParam('sender_name'),
                        'email' => $this->getParam('sender_email')
                    ]
                ]
            ],
            'cc' => $this->getCarbonCopy(),
            'bcc' => $this->getBlindCarbonCopy()
        ];

        if ($replyTo = $this->getReplyTo()) {
            $body['content']['reply_to'] = $replyTo;
        }

        if (!empty($this->getParam('attachments'))) {
            $body['content']['attachments'] = $this->getAttachments();
        }

        $params = [
            'body' => json_encode($body),
            'headers' => $this->getRequestHeaders()
        ];

        $params = array_merge($params, $this->getDefaultParams());

        $this->response = wp_safe_remote_post($this->url, $params);

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_SPARKPOST_API_KEY') ? FLUENTMAIL_SPARKPOST_API_KEY : '';
        }
        $this->settings = $settings;
        return $this;
    }

    protected function getFrom()
    {
        $email = $this->getParam('sender_email');

        if ($name = $this->getParam('sender_name')) {
            $from = $name . ' <' . $email . '>';
        } else {
            $from = $email;
        }

        return $from;
    }

    protected function getReplyTo()
    {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            $replyTo = reset($replyTo);
        
            return $replyTo['email'];
        }
    }

    protected function getCarbonCopy()
    {
        $address = [];

        foreach ($this->getParam('headers.cc') as $cc) {
            $address[] = [
                'address' => [
                    'email' => $cc['email']
                ]
            ];
        }

        return $address;
    }

    protected function getBlindCarbonCopy()
    {
        $address = [];

        foreach ($this->getParam('headers.bcc') as $bcc) {
            $address[] = [
                'address' => [
                    'email' => $bcc['email']
                ]
            ];
        }

        return $address;
    }

    protected function getAttachments()
    {
        $data = [];

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

            $data[] = [
                'name' => $fileName,
                'type' => $filetype,
                'content' => base64_encode($file)
            ];
        }

        return $data;
    }

    protected function getCustomEmailHeaders()
    {
        return [
            'X-Mailer' => 'FluentMail - SparkPost'
        ];
    }

    protected function getRequestHeaders()
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => $this->getSetting('api_key')
        ];
    }

    public function isEmailSent()
    {
        $isSent = wp_remote_retrieve_response_code($this->response) == $this->emailSentCode;

        if (
            $isSent &&
            isset($this->response['response']) &&
            $this->response['response']['message'] != 'Accepted'
        ) {
            return false;
        }

        return $isSent;
    }

    protected function handleSuccess()
    {
        $response = (array) json_decode($this->response['body'], true);

        return $this->processResponse(['response' => $response], true);
    }

    protected function handleFailure()
    {
        $response = $this->getResponseError();

        $this->processResponse(['response' => $response], false);

        $this->fireWPMailFailedAction($response);
    }

    public function getResponseError()
    {
        $response = $this->response;
        
        $body = (array) wp_remote_retrieve_body($response);

        $body = json_decode($body[0], true);

        $responseErrors = [];
        
        if (!empty($body['errors'])) {
            $responseErrors = $body['errors'];
        } elseif (!empty($body['error'])) {
            $responseErrors = $body['error'];
        }

        $errors = [];

        if (!empty($responseErrors) && is_array($responseErrors)) {
            
            foreach ($responseErrors as $error) {
                if (array_key_exists('message', $error)) {
                    $errors[] = $error['message'];
                }
            }
        } else {
            $errors = [$responseErrors];
        }

        $errors = array_map('esc_textarea', $errors);

        return [
            'message' => $response['response']['message'],
            'code' => $response['response']['code'],
            'errors' => $errors
        ];
    }
}
