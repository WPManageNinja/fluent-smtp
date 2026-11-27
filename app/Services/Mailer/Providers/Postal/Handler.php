<?php

namespace FluentMail\App\Services\Mailer\Providers\Postal;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    public function send()
    {
        if ($this->preSend() && $this->phpMailer->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong.', 'fluent-smtp'), []));
    }

    public function postSend()
    {
        /*
         * Postal's structured /send/message API only accepts name, content_type
         * and data on attachments — no Content-ID. HTML that references an
         * embedded image as cid:... would arrive without a matching part, so
         * those messages go out as a prepared RFC 2822 payload on /send/raw,
         * which keeps PHPMailer's Content-ID and disposition intact.
         */
        if ($this->hasInlineAttachments()) {
            return $this->postSendRaw();
        }

        return $this->postSendMessage();
    }

    protected function postSendMessage()
    {
        $body = [
            'to'      => $this->getTo(),
            'from'    => $this->getFormattedFrom(),
            'subject' => $this->getSubject(),
        ];

        if ($replyTo = $this->getReplyTo()) {
            $body['reply_to'] = $replyTo;
        }

        if ($cc = $this->getCarbonCopy()) {
            $body['cc'] = $cc;
        }

        if ($bcc = $this->getBlindCarbonCopy()) {
            $body['bcc'] = $bcc;
        }

        $contentType = $this->getHeader('content-type');

        if ($contentType == 'text/html') {
            $body['html_body'] = $this->getParam('message');
            if ($this->phpMailer->AltBody) {
                $body['plain_body'] = $this->phpMailer->AltBody;
            }
        } else if ($contentType == 'multipart/alternative') {
            $body['html_body'] = $this->getParam('message');
            $body['plain_body'] = $this->phpMailer->AltBody;
        } else {
            $body['plain_body'] = $this->getParam('message');
        }

        if (!empty($this->getParam('attachments'))) {
            $attachments = $this->getAttachments();
            if ($attachments) {
                $body['attachments'] = $attachments;
            }
        }

        $headers = $this->getCustomHeaders();
        if ($headers) {
            $body['headers'] = $headers;
        }

        return $this->dispatch($this->getApiUrl('send/message'), $body);
    }

    protected function postSendRaw()
    {
        $body = [
            'mail_from' => $this->getParam('sender_email'),
            'rcpt_to'   => $this->getEnvelopeRecipients(),
            'data'      => base64_encode($this->phpMailer->getSentMIMEMessage()),
        ];

        return $this->dispatch($this->getApiUrl('send/raw'), $body);
    }

    protected function dispatch($url, array $body)
    {
        $params = [
            'headers' => $this->getRequestHeaders(),
            'body'    => wp_json_encode($body),
        ];

        $params = array_merge($params, $this->getDefaultParams());

        $response = wp_safe_remote_post($url, $params);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);
            $responseCode = wp_remote_retrieve_response_code($response);
            $decoded = \json_decode($responseBody, true);

            $isOk = $responseCode == $this->emailSentCode
                && is_array($decoded)
                && Arr::get($decoded, 'status') === 'success';

            if ($isOk) {
                $returnResponse = [
                    'id'      => Arr::get($decoded, 'data.message_id'),
                    'message' => __('Email sent successfully.', 'fluent-smtp'),
                ];
            } else {
                $errorMessage = Arr::get($decoded, 'data.message');
                if (!$errorMessage) {
                    $errorMessage = Arr::get($decoded, 'data.code', __('Unknown Error', 'fluent-smtp'));
                }
                $returnResponse = new \WP_Error($responseCode ?: 400, $errorMessage, is_array($decoded) ? $decoded : $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if ($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_POSTAL_API_KEY') ? FLUENTMAIL_POSTAL_API_KEY : '';
        }

        $this->settings = $settings;

        return $this;
    }

    protected function getApiUrl($path = 'send/message')
    {
        $base = rtrim((string)$this->getSetting('server_url'), '/');
        $url = $base . '/api/v1/' . ltrim($path, '/');

        return apply_filters('fluentsmtp_postal_api_url', $url, $base, $path);
    }

    protected function hasInlineAttachments()
    {
        $attachments = $this->getParam('attachments');

        if (empty($attachments) || !is_array($attachments)) {
            return false;
        }

        foreach (self::inlineContentIds($attachments) as $contentId) {
            if ($contentId !== '') {
                return true;
            }
        }

        return false;
    }

    protected function getEnvelopeRecipients()
    {
        $emails = [];

        foreach (['to', 'headers.cc', 'headers.bcc'] as $key) {
            foreach ((array)$this->getParam($key) as $recipient) {
                if (!empty($recipient['email'])) {
                    $emails[] = $recipient['email'];
                }
            }
        }

        return array_values(array_unique($emails));
    }

    protected function getReplyTo()
    {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            $replyTo = reset($replyTo);

            return isset($replyTo['name'])
                ? self::formatQuotedAddress($replyTo['email'], $replyTo['name'])
                : $replyTo['email'];
        }
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
        if (empty($recipients) || !is_array($recipients)) {
            return [];
        }

        return array_values(array_map(function ($recipient) {
            return isset($recipient['name'])
                ? self::formatAddress($recipient['email'], $recipient['name'])
                : $recipient['email'];
        }, $recipients));
    }

    protected function getAttachments()
    {
        $data = [];

        foreach ($this->getParam('attachments') as $attachment) {
            try {
                $file = self::attachmentContent($attachment);
            } catch (\Exception $e) {
                $this->logAttachmentFailure('Postal', $e);
                continue;
            }

            $data[] = [
                'name'         => self::attachmentName($attachment),
                'content_type' => self::attachmentType($attachment),
                'data'         => base64_encode($file),
            ];
        }

        return $data;
    }

    protected function getCustomHeaders()
    {
        $headers = [];
        $customHeaders = $this->phpMailer->getCustomHeaders();

        if (empty($customHeaders)) {
            return $headers;
        }

        foreach ($customHeaders as $header) {
            if (!isset($header[0], $header[1])) {
                continue;
            }
            $headers[$header[0]] = $header[1];
        }

        return $headers;
    }

    protected function getRequestHeaders()
    {
        return [
            'Accept'           => 'application/json',
            'Content-Type'     => 'application/json',
            'X-Server-API-Key' => $this->getSetting('api_key'),
        ];
    }
}
