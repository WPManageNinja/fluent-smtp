<?php

namespace FluentMail\App\Services\Mailer\Providers\Smtp2Go;

use WP_Error as WPError;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\SendGrid\ValidatorTrait;

class Handler extends BaseHandler {
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $url = 'https://api.smtp2go.com/v3/email/send';

    public function send() {
        if ($this->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong!', 'fluent-smtp'), []));
    }

    public function postSend() {
        $body = [
            'sender'    => $this->getFrom(),
            'to'        => $this->getTo(),
            'cc'        => $this->getCarbonCopy(),
            'bcc'       => $this->getBlindCarbonCopy(),
            'subject'   => $this->getSubject(),
            'html_body' => $this->getBody(),
            'text_body' => $this->phpMailer->AltBody
        ];

        if ($replyTo = $this->getReplyTo()) {
            $body['custom_headers'][] = [
                'header' => 'Reply-To',
                'value'  => $replyTo
            ];
        }


        if (!empty($this->getParam('attachments'))) {
            $body['attachments'] = $this->getAttachments();
        }

        $params = [
            'body'    => json_encode($body),
            'headers' => $this->getRequestHeaders()
        ];

        $params = array_merge($params, $this->getDefaultParams());

        $response = wp_safe_remote_post($this->url, $params);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);
            $responseCode = wp_remote_retrieve_response_code($response);
            $isOKCode     = $responseCode == $this->emailSentCode;
            $responseBody = \json_decode($responseBody, true);

            if ($isOKCode) {
                $returnResponse = [
                    'email_id'  => Arr::get($responseBody, 'data.email_id'),
                    'succeeded' => Arr::get($responseBody, 'data.succeeded'),
                ];
            } else {
                $returnResponse = new \WP_Error($responseCode, Arr::get($responseBody, 'data.error', 'Unknown Error'), $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    protected function getFrom() {
        $from = $this->getParam('sender_email');

        if ($name = $this->getParam('sender_name')) {
            $from = $name . ' <' . $from . '>';
        }

        return $from;
    }

    protected function getReplyTo() {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            $replyTo = reset($replyTo);

            return $replyTo['email'];
        }
    }

    protected function getRecipients($recipients) {
        return array_map(function ($recipient) {
            return isset($recipient['name'])
                ? $recipient['name'] . ' <' . $recipient['email'] . '>'
                : $recipient['email'];
        }, $recipients);
    }

    protected function getTo() {
        return $this->getRecipients($this->getParam('to'));
    }

    protected function getCarbonCopy() {
        return $this->getRecipients($this->getParam('headers.cc'));
    }

    protected function getBlindCarbonCopy() {
        return $this->getRecipients($this->getParam('headers.bcc'));
    }

    protected function getBody() {
        return $this->getParam('message');
    }

    protected function getAttachments() {
        $data = [];

        foreach ($this->getParam('attachments') as $attachment) {
            $file = false;

            try {
                if (is_file($attachment[0]) && is_readable($attachment[0])) {
                    $fileName = basename($attachment[0]);
                    $file     = file_get_contents($attachment[0]);
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
                'mimetype' => $filetype,
                'filename' => $fileName,
                'fileblob' => base64_encode($file)
            ];
        }

        return $data;
    }

    protected function getCustomEmailHeaders() {
        return [];
    }

    protected function getRequestHeaders() {
        return [
            'Content-Type'      => 'application/json',
            'X-Smtp2go-Api-Key' => $this->getSetting('api_key')
        ];
    }

    public function setSettings($settings) {
        if ($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_SMTP2GO_API_KEY') ? FLUENTMAIL_SMTP2GO_API_KEY : '';
        }
        $this->settings = $settings;

        return $this;
    }
}
