<?php

namespace FluentMail\App\Services\Mailer\Providers\SendGrid;

use WP_Error as WPError;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\SendGrid\ValidatorTrait;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 202;

    protected $url = 'https://api.sendgrid.com/v3/mail/send';

    public function send()
    {
        if ($this->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong!', 'fluent-smtp'), []));
    }

    public function postSend()
    {
        $body = [
            'from'             => $this->getFrom(),
            'personalizations' => $this->getRecipients(),
            'subject'          => $this->getSubject(),
            'content'          => $this->getBody()
        ];

        if ($replyTo = $this->getReplyTo()) {
            $body['reply_to'] = $replyTo;
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
            $isOKCode = $responseCode == $this->emailSentCode;
            $responseBody = \json_decode($responseBody, true);

            if ($isOKCode) {
                $returnResponse = [
                    'code'    => 202,
                    'message' => Arr::get($responseBody, 'message')
                ];
            } else {
                $returnResponse = new \WP_Error($responseCode, Arr::get($responseBody, 'errors.0.message', 'Unknown Error'), $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
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

    protected function getReplyTo()
    {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            return reset($replyTo);
        }
    }

    protected function getRecipients()
    {
        $recipients = [
            'to'  => $this->getTo(),
            'cc'  => $this->getCarbonCopy(),
            'bcc' => $this->getBlindCarbonCopy(),
        ];

        $recipients = array_filter($recipients);

        foreach ($recipients as $key => $recipient) {
            $array = array_map(function ($recipient) {
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
        return $this->getParam('headers.cc');
    }

    protected function getBlindCarbonCopy()
    {
        return $this->getParam('headers.bcc');
    }

    protected function getBody()
    {
        $contentType = $this->getParam('headers.content-type');

        if ($contentType == 'multipart/alternative') {
            return [
                [
                    'value' => $this->phpMailer->AltBody,
                    'type'  => 'text/plain'
                ],
                [
                    'value' => $this->getParam('message'),
                    'type'  => 'text/html'
                ]
            ];
        }

        return [
            [
                'value' => $this->getParam('message'),
                'type'  => $contentType
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
                    $contentId = wp_hash($attachment[0]);
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
                'type'        => $filetype,
                'filename'    => $fileName,
                'disposition' => 'attachment',
                'content_id'  => $contentId,
                'content'     => base64_encode($file)
            ];
        }

        return $data;
    }

    protected function getCustomEmailHeaders()
    {
        return [];
    }

    protected function getRequestHeaders()
    {
        return [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->getSetting('api_key')
        ];
    }

    public function setSettings($settings)
    {
        if ($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_SENDGRID_API_KEY') ? FLUENTMAIL_SENDGRID_API_KEY : '';
        }
        $this->settings = $settings;
        return $this;
    }
}
