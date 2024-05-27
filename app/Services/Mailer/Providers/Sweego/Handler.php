<?php

namespace FluentMail\App\Services\Mailer\Providers\Sweego;

use Exception;
use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\Sweego\ValidatorTrait;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $url = 'https://api.sweego.io/send';

    public function send()
    {
        if ($this->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong!', 'fluent-smtp'), []));
    }

    public function postSend()
    {
        $subject = $this->getParam('subject');
        error_log('Subject field: ' . print_r($subject, true)); 

        if (empty($subject)) {
            throw new Exception(__('The "subject" field is required.', 'fluent-smtp'), 422);
        }

        // Construisez le corps de la requête
        $body = [
            'channel' => 'email',
            'provider' => 'sweego',
            'recipients' => $this->getRecipients(),
            'from' => $this->getFrom(),
            'subject' => $subject,
            'message-html' => $this->getBody()
        ];

        $contentType = $this->getParam('headers.content-type');
        if ($contentType == 'text/plain') {
            $body['message-txt'] = $this->getBody();
            unset($body['message-html']);
        }

        // Ajoutez le template-id si spécifié
        $templateId = $this->getParam('template-id');
        if (!empty($templateId)) {
            $body['template-id'] = $templateId;
        }

        if ($replyTo = $this->getReplyTo()) {
            $body['reply_to'] = $replyTo;
        }

        if (!empty($this->getParam('attachments'))) {
            $body['attachments'] = $this->getAttachments();
        }

        $this->addOptionalParameters($body);

        error_log(print_r($body, true));

        $params = [
            'body' => json_encode($body),
            'headers' => $this->getRequestHeaders(),
            'timeout' => 45
        ];

        $response = wp_safe_remote_post($this->url, $params);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);
            $responseCode = wp_remote_retrieve_response_code($response);
            $isOKCode = $responseCode == $this->emailSentCode;
            $responseBody = json_decode($responseBody, true);

            if ($isOKCode) {
                $returnResponse = [
                    'code' => 202,
                    'message' => Arr::get($responseBody, 'message')
                ];
            } else {
                $returnResponse = new \WP_Error($responseCode, Arr::get($responseBody, 'errors.0.message', 'Unknown Error'), $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($response);
    }

    protected function addOptionalParameters(&$body)
    {
        foreach (['variables', 'campaign-tags', 'list-unsub'] as $param) {
            $value = $this->getParam($param);
            if (!empty($value)) {
                $body[$param] = is_string($value) ? json_decode($value, true) : $value;
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('JSON decode error for ' . $param . ': ' . json_last_error_msg());
                }
            }
        }

        $campaignType = $this->getParam('campaign-type');
        if (!empty($campaignType)) {
            $body['campaign-type'] = $campaignType;
        }

        $dryRun = $this->getParam('dry-run');
        if (!is_null($dryRun)) {
            $body['dry-run'] = (bool) $dryRun;
        }

        $headers = $this->getParam('headers');
        if (is_array($headers)) {
            foreach (['reply-to', 'cc', 'bcc'] as $header) {
                if (isset($headers[$header]) && is_string($headers[$header])) {
                    $body['headers'][$header] = $headers[$header];
                }
            }
        }
    }

    protected function getReplyTo()
    {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            return reset($replyTo);
        }
    }

    protected function getTo()
    {
        return $this->getParam('to');
    }

    protected function getCarbonCopy()
    {
        return $this->getParam('headers.cc');
    }

    protected function getBlindCarbonCopy()
    {
        return $this->getParam('headers.bcc');
    }

    protected function getBody()
    {
        return $this->getParam('message');
    }

    protected function getRecipients()
    {
        $to = $this->getTo();
        $cc = $this->getCarbonCopy();
        $bcc = $this->getBlindCarbonCopy();

        $recipients = [];

        if (!empty($to)) {
            foreach ($to as $recipient) {
                $recipients[] = [
                    'email' => $recipient['email'],
                    'name' => $recipient['name'] ?? ''
                ];
            }
        }

        if (!empty($cc)) {
            foreach ($cc as $recipient) {
                $recipients[] = [
                    'email' => $recipient['email'],
                    'name' => $recipient['name'] ?? ''
                ];
            }
        }

        if (!empty($bcc)) {
            foreach ($bcc as $recipient) {
                $recipients[] = [
                    'email' => $recipient['email'],
                    'name' => $recipient['name'] ?? ''
                ];
            }
        }

        return $recipients;
    }

    protected function getFrom()
    {
        $from = [
            'email' => $this->getParam('sender_email')
        ];

        if ($name = $this->getParam('sender_name')) {
            $from['name'] = $name;
        }

        return $from;
    }

    protected function isHtmlEmail()
    {
        $contentType = $this->getParam('headers.content-type');
        return strpos($contentType, 'text/html') !== false;
    }

    protected function getRequestHeaders()
    {
        return [
            'Content-Type' => 'application/json',
            'Api-Key' => $this->getSetting('api_key')
        ];
    }
}
