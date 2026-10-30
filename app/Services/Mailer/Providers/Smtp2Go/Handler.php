<?php

namespace FluentMail\App\Services\Mailer\Providers\Smtp2Go;

use WP_Error as WPError;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\Smtp2Go\ValidatorTrait;

class Handler extends BaseHandler {
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $url = 'https://api.smtp2go.com/v3/email/send';

    /*
     * api.smtp2go.com resolves to SMTP2GO's nodes on every continent, so a sender
     * in Europe regularly lands on a US or AU node. The regional hosts pin requests
     * to one region and accept the same API keys.
     */
    protected $regionHosts = [
        'global' => 'api.smtp2go.com',
        'eu'     => 'eu-api.smtp2go.com',
        'us'     => 'us-api.smtp2go.com',
        'au'     => 'au-api.smtp2go.com',
    ];

    public function send() {
        if ($this->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong.', 'fluent-smtp'), []));
    }

    public function postSend() {
        $body = [
            'sender'    => $this->getFrom(),
            'to'        => $this->getTo(),
            'cc'        => $this->getCarbonCopy(),
            'bcc'       => $this->getBlindCarbonCopy(),
            'subject'   => $this->getSubject(),
            'html_body' => $this->getBody(),
            'text_body' => $this->phpMailer->AltBody,
            /*
             * Without fastaccept SMTP2GO holds the request open until the email is
             * sent, which can outlast the HTTP timeout: the email is delivered but
             * logged as failed. With it the email is queued and accepted at once.
             */
            'fastaccept' => (bool) apply_filters('fluentsmtp_smtp2go_fastaccept', true)
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

        $response = wp_safe_remote_post($this->getApiUrl(), $params);

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
                $returnResponse = new \WP_Error($responseCode, Arr::get($responseBody, 'data.error', __('Unknown Error', 'fluent-smtp')), $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    protected function getApiUrl() {
        $region = $this->getSetting('region');

        $url = isset($this->regionHosts[$region])
            ? 'https://' . $this->regionHosts[$region] . '/v3/email/send'
            : $this->url;

        return apply_filters('fluentsmtp_smtp2go_api_url', $url, $region);
    }

    protected function getFrom() {
        $from = $this->getParam('sender_email');

        if ($name = $this->getParam('sender_name')) {
            $from = self::formatAddress($from, $name);
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
                ? self::formatAddress($recipient['email'], $recipient['name'])
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
            try {
                $file = self::attachmentContent($attachment);
            } catch (\Exception $e) {
                $this->logAttachmentFailure('Smtp2Go', $e);
                continue;
            }

            $data[] = [
                'mimetype' => self::attachmentType($attachment),
                'filename' => self::getAttachmentName($attachment),
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
