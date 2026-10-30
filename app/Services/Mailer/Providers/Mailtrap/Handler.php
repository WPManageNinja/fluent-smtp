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
        $body = [
            'from'    => $this->getFrom(),
            'to'      => $this->getTo(),
            'subject' => $this->getSubject(),
        ];

        $contentType = $this->getHeader('content-type');

        /*
         * The API rejects an empty `text` or `html` string, so the plain-text
         * part of a multipart message is sent only when PHPMailer has one.
         */
        if ($contentType == 'text/html') {
            $body['html'] = $this->getParam('message');
        } elseif ($contentType == 'multipart/alternative') {
            $body['html'] = $this->getParam('message');
            if ($this->phpMailer->AltBody) {
                $body['text'] = $this->phpMailer->AltBody;
            }
        } else {
            $body['text'] = $this->getParam('message');
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
                $errorMessage = $errors ? implode(' ', $errors) : __('Unknown Error', 'fluent-smtp');
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
            $file = false;
            $fileName = null;

            try {
                $file = $this->secureFileRead($attachment[0]);
                $fileName = $this->getAttachmentName($attachment);
            } catch (\Exception $e) {
                $this->logAttachmentFailure('Mailtrap', $e);
                $file = false;
            }

            if ($file === false) {
                continue;
            }

            $data[] = [
                'filename'    => $fileName,
                'content'     => base64_encode($file),
                'type'        => $this->determineMimeContentType($attachment[0]),
                'disposition' => 'attachment',
            ];
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

    protected function determineMimeContentType($filename)
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filename);
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimeType;
        }

        return 'application/octet-stream';
    }
}
