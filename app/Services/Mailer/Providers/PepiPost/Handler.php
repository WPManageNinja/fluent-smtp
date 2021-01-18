<?php

namespace FluentMail\App\Services\Mailer\Providers\PepiPost;

use WP_Error as WPError;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\PepiPost\ValidatorTrait;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 202;

    protected $url = 'https://api.pepipost.com/v5/mail/send';

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
            'from' => $this->getFrom(),
            'personalizations' => $this->getRecipients(),
            'subject' => $this->getSubject(),
            'content' => $this->getBody(),
            'headers' => $this->getCustomEmailHeaders()
        ];

        if ($replyTo = $this->getReplyTo()) {
            $body['reply_to'] = $replyTo;
        }

        if (!empty($this->getParam('attachments'))) {
            $body['attachments'] = $this->getAttachments();
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
            $settings['api_key'] = defined('FLUENTMAIL_PEPIPOST_API_KEY') ? FLUENTMAIL_PEPIPOST_API_KEY : '';
        }
        $this->settings = $settings;
        return $this;
    }

    protected function getFrom()
    {
        return [
            'name' => $this->getParam('sender_name'),
            'email' => $this->getParam('sender_email')
        ];
    }

    protected function getReplyTo()
    {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            $replyTo = reset($replyTo);
        
            return $replyTo['email'];
        }
    }

    protected function getRecipients()
    {
        $recipients = [
            'to' => $this->getTo(),
            'cc' => $this->getCarbonCopy(),
            'bcc' => $this->getBlindCarbonCopy(),
        ];

        $recipients = array_filter($recipients);

        foreach ($recipients as $key => $recipient) {
            $array = array_map(function($recipient) {
                return isset($recipient['name'])
                ? $recipient['name'] . ' <' . $recipient['email'] . '>'
                : $recipient['email'];
           }, $recipient);

            $this->attributes['formatted'][$key] = implode(', ', $array);
        }

        return [$recipients];
    }

    protected function getTo()
    {
        return $this->getParam('to');
    }

    protected function getCarbonCopy()
    {
        return array_map(function($cc) {
            return ['email' => $cc['email']];
        }, $this->getParam('headers.cc'));
    }

    protected function getBlindCarbonCopy()
    {
       return array_map(function($bcc) {
            return ['email' => $bcc['email']];
        }, $this->getParam('headers.bcc'));
    }

    protected function getBody()
    {
        return [
            [
                'type' => 'html',
                'value' => $this->getParam('message')
            ]
        ];
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
                }
            } catch (\Exception $e) {
                $file = false;
            }

            if ($file === false) {
                continue;
            }

            $data[] = [
                'name' => $fileName,
                'content' => base64_encode($file)
            ];
        }

        return $data;
    }

    protected function getCustomEmailHeaders()
    {
        return [
            'X-Mailer' => 'FluentMail - PepiPost'
        ];
    }

    protected function getRequestHeaders()
    {
        return [
            'Content-Type' => 'application/json',
            'api_key' => $this->getSetting('api_key')
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
                    $extra = '';

                    if (array_key_exists('field', $error) && !empty($error['field'])) {
                        $extra .= $error['field'] . '.';
                    }

                    if (array_key_exists('help', $error) && !empty($error['help'])) {
                        $extra .= $error['help'];
                    }

                    $errors[] = $error['message'] .
                    (!empty($extra) ? ' - ' . $extra : '') .
                    ' ' . $error['description'];
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
