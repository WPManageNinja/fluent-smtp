<?php

namespace FluentMail\App\Services\Mailer\Providers\Mailtrap;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $url = 'https://send.api.mailtrap.io/api/send';

    protected $bulkUrl = 'https://bulk.api.mailtrap.io/api/send';

    public function send()
    {
        if ($this->preSend() && $this->phpMailer->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong.', 'fluent-smtp'), []));
    }

    public function postSend()
    {
        $subject = (string)$this->getSubject();

        $body = [
            'from'    => $this->getFrom(),
            'to'      => $this->getTo(),
            // The API requires a non-empty subject (minLength 1); mail clients
            // show an empty one as "(no subject)" anyway.
            'subject' => $subject !== '' ? $subject : __('(no subject)', 'fluent-smtp'),
        ];

        /*
         * Pick the parts from what PHPMailer holds after its preSend(), not from
         * the content type captured before it: a phpmailer_init listener may add
         * a plain-text AltBody to an HTML message late. The API rejects an empty
         * `text` or `html` string, so an empty part is left out, and a message
         * with no body at all (an attachment-only notification) sends one space.
         */
        $message = (string)$this->getParam('message');
        $altBody = (string)$this->phpMailer->AltBody;
        $contentType = strtolower((string)$this->phpMailer->ContentType . ' ' . (string)$this->getHeader('content-type'));
        $isHtml = strpos($contentType, 'html') !== false || strpos($contentType, 'multipart/alternative') !== false || $altBody !== '';

        if ($isHtml) {
            if ($message !== '') {
                $body['html'] = $message;
            }
            if ($altBody !== '') {
                $body['text'] = $altBody;
            }
        } elseif ($message !== '') {
            $body['text'] = $message;
        }

        if (!isset($body['html']) && !isset($body['text'])) {
            $body['text'] = ' ';
        }

        if ($replyTo = $this->getReplyTo()) {
            $body['reply_to'] = $replyTo;
        }

        if ($cc = $this->getCarbonCopy()) {
            $body['cc'] = $cc;
        }

        if ($bcc = $this->getBlindCarbonCopy()) {
            $body['bcc'] = $bcc;
        }

        if (!empty($this->getParam('attachments'))) {
            $body['attachments'] = $this->getAttachments();
        }

        $customHeaders = $this->phpMailer->getCustomHeaders();
        if (!empty($customHeaders)) {
            $headers = [];
            foreach ($customHeaders as $header) {
                $headers[$header[0]] = $header[1];
            }
            if (!empty($headers)) {
                $body['headers'] = $headers;
            }
        }

        $params = array_merge([
            'headers' => $this->getRequestHeaders(),
            'body'    => wp_json_encode($body),
        ], $this->getDefaultParams());

        $response = wp_safe_remote_post($this->getEndpoint(), $params);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);
            $responseCode = wp_remote_retrieve_response_code($response);

            $responseBody = \json_decode($responseBody, true);

            $isOKCode = $responseCode == $this->emailSentCode && Arr::get($responseBody, 'success');

            if ($isOKCode) {
                $returnResponse = [
                    'message_ids' => Arr::get($responseBody, 'message_ids', []),
                    'message'     => __('Email sent successfully via Mailtrap', 'fluent-smtp')
                ];
            } else {
                $errors = array_filter((array)Arr::get($responseBody, 'errors', []), 'is_string');
                $errorMessage = $errors
                    ? implode(' ', $errors)
                    : sprintf(__('Mailtrap API error (HTTP %s)', 'fluent-smtp'), $responseCode ?: '?');
                $returnResponse = new \WP_Error($responseCode ?: 400, $errorMessage, $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if (Arr::get($settings, 'key_store') == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_MAILTRAP_API_KEY') ? FLUENTMAIL_MAILTRAP_API_KEY : '';
        }

        $this->settings = $settings;
        return $this;
    }

    protected function getEndpoint()
    {
        return $this->getSetting('message_stream') == 'bulk' ? $this->bulkUrl : $this->url;
    }

    /*
     * GET /api/accounts answers 200 for an account token and for the token
     * Mailtrap creates with each sending domain, which can reach that domain
     * only. Only a 401 proves the token wrong. A network error or any other
     * answer says nothing about the token, so it does not block a save or fail
     * the daily health check.
     */
    public function checkConnection($connection)
    {
        $this->setSettings($connection);

        $response = wp_safe_remote_get('https://mailtrap.io/api/accounts', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getSetting('api_key'),
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (wp_remote_retrieve_response_code($response) == 401) {
            $this->throwValidationException([
                'api_key' => [
                    'required' => __('Invalid Mailtrap API token.', 'fluent-smtp')
                ]
            ]);
        }

        return true;
    }

    protected function getFrom()
    {
        $from = [
            'email' => $this->getParam('sender_email'),
        ];

        if ($name = $this->getParam('sender_name')) {
            $from['name'] = $name;
        }

        return $from;
    }

    protected function getReplyTo()
    {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            $replyTo = reset($replyTo);
            return empty($replyTo['email']) ? null : $replyTo;
        }

        return null;
    }

    protected function getTo()
    {
        return $this->formatRecipients($this->getParam('to'));
    }

    protected function getCarbonCopy()
    {
        return $this->formatRecipients($this->getParam('headers.cc'));
    }

    protected function getBlindCarbonCopy()
    {
        return $this->formatRecipients($this->getParam('headers.bcc'));
    }

    protected function formatRecipients($recipients)
    {
        if (empty($recipients)) {
            return [];
        }

        $list = [];
        foreach ($recipients as $recipient) {
            if (empty($recipient['email'])) {
                continue;
            }
            $list[] = $recipient;
        }

        return $list;
    }

    protected function getAttachments()
    {
        $data = [];

        foreach ($this->getParam('attachments') as $attachment) {
            try {
                // Reads a file through secureFileRead(), or takes in-memory data
                // (addStringAttachment()) as it is.
                $file = self::attachmentContent($attachment);
            } catch (\Exception $e) {
                $this->logAttachmentFailure('Mailtrap', $e);
                continue;
            }

            $item = [
                'filename'    => self::getAttachmentName($attachment),
                'content'     => base64_encode($file),
                'type'        => self::attachmentType($attachment),
                'disposition' => 'attachment',
            ];

            // An image embedded with addEmbeddedImage() is referenced from the
            // HTML as cid:<id>; Mailtrap needs it inline with that content_id.
            if (Arr::get($attachment, 6) === 'inline' && (string)Arr::get($attachment, 7) !== '') {
                $item['disposition'] = 'inline';
                $item['content_id'] = (string)$attachment[7];
            }

            $data[] = $item;
        }

        return $data;
    }

    protected function getRequestHeaders()
    {
        return [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->getSetting('api_key'),
        ];
    }
}
