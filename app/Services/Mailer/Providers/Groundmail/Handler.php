<?php

namespace FluentMail\App\Services\Mailer\Providers\Groundmail;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $url = 'https://api.groundmail.dev/emails';

    /*
     * Headers the API sets itself. A request that names one of them in `headers`
     * is refused as a whole, so they are left out of the custom headers here:
     * the addresses travel in their own fields and the rest belong to the
     * message the API builds.
     */
    protected $reservedHeaders = [
        'authentication-results',
        'bcc',
        'cc',
        'content-transfer-encoding',
        'content-type',
        'date',
        'delivered-to',
        'dkim-signature',
        'from',
        'message-id',
        'mime-version',
        'received',
        'reply-to',
        'return-path',
        'sender',
        'subject',
        'to',
    ];

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

        if ($headers = $this->getCustomEmailHeaders()) {
            $body['headers'] = $headers;
        }

        $params = array_merge([
            'headers' => $this->getRequestHeaders(),
            'body'    => wp_json_encode($body),
        ], $this->getDefaultParams());

        $response = wp_safe_remote_post($this->url, $params);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);
            $responseCode = wp_remote_retrieve_response_code($response);

            $responseBody = \json_decode($responseBody, true);

            $messageId = Arr::get($responseBody, 'id');

            if ($responseCode == $this->emailSentCode && $messageId) {
                $returnResponse = [
                    'id'     => $messageId,
                    'status' => Arr::get($responseBody, 'status', '')
                ];
            } else {
                $errorMessage = Arr::get($responseBody, 'error.message', __('Unknown Error', 'fluent-smtp'));
                $returnResponse = new \WP_Error($responseCode ?: 400, $errorMessage, $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if (Arr::get($settings, 'key_store') == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_GROUNDMAIL_API_KEY') ? FLUENTMAIL_GROUNDMAIL_API_KEY : '';
        }

        $this->settings = $settings;
        return $this;
    }

    /*
     * Listing one email needs only a valid key. A 401 means the key is wrong. A
     * send-only key gets a 403 here and is still a working key, and a network
     * error or any other answer says nothing about the key, so none of those
     * block a save.
     */
    public function checkConnection($connection)
    {
        $this->setSettings($connection);

        $response = wp_safe_remote_get($this->url . '?limit=1', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getSetting('api_key'),
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (wp_remote_retrieve_response_code($response) == 401) {
            $this->throwValidationException([
                'api_key' => [
                    'required' => __('Invalid Groundmail API key.', 'fluent-smtp')
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
        return $this->formatRecipients($this->getParam('headers.reply-to'));
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
            $list[] = $recipient['email'];
        }

        return $list;
    }

    protected function getCustomEmailHeaders()
    {
        $headers = [];

        foreach ($this->phpMailer->getCustomHeaders() as $header) {
            if (in_array(strtolower(trim($header[0])), $this->reservedHeaders, true)) {
                continue;
            }
            $headers[$header[0]] = $header[1];
        }

        return $headers;
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
                $this->logAttachmentFailure('Groundmail', $e);
                $file = false;
            }

            if ($file === false) {
                continue;
            }

            $data[] = [
                'name'         => $fileName,
                'content_type' => $this->determineMimeContentType($attachment[0]),
                'data'         => base64_encode($file),
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
