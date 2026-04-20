<?php

namespace FluentMail\App\Services\Mailer\Providers\Cloudflare;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $url = 'https://api.cloudflare.com/client/v4/accounts/{account_id}/email/sending/send';

    public function send()
    {
        if ($this->preSend() && $this->phpMailer->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong!', 'fluent-smtp'), []));
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
            $body['text'] = $this->phpMailer->AltBody;
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
                    'id'      => Arr::get($responseBody, 'result.id', ''),
                    'message' => __('Email sent successfully via Cloudflare', 'fluent-smtp')
                ];
            } else {
                $errorMessage = Arr::get($responseBody, 'errors.0.message', 'Unknown Error');
                $returnResponse = new \WP_Error($responseCode ?: 400, $errorMessage, $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if (Arr::get($settings, 'key_store') == 'wp_config') {
            $settings['api_key']    = defined('FLUENTMAIL_CLOUDFLARE_API_KEY') ? FLUENTMAIL_CLOUDFLARE_API_KEY : '';
            $settings['account_id'] = defined('FLUENTMAIL_CLOUDFLARE_ACCOUNT_ID') ? FLUENTMAIL_CLOUDFLARE_ACCOUNT_ID : Arr::get($settings, 'account_id');
        }

        $this->settings = $settings;
        return $this;
    }

    protected function getEndpoint()
    {
        $accountId = rawurlencode((string) $this->getSetting('account_id'));
        return str_replace('{account_id}', $accountId, $this->url);
    }

    public function getConnectionInfo($connection)
    {
        $settings = $connection;
        if (Arr::get($settings, 'key_store') == 'wp_config') {
            $settings['api_key']    = defined('FLUENTMAIL_CLOUDFLARE_API_KEY') ? FLUENTMAIL_CLOUDFLARE_API_KEY : '';
            $settings['account_id'] = defined('FLUENTMAIL_CLOUDFLARE_ACCOUNT_ID') ? FLUENTMAIL_CLOUDFLARE_ACCOUNT_ID : Arr::get($settings, 'account_id');
        }

        $accountId = Arr::get($settings, 'account_id');
        $apiKey    = Arr::get($settings, 'api_key');

        $extraRows = [
            [
                'title'   => __('Cloudflare Account ID', 'fluent-smtp'),
                'content' => esc_html((string) $accountId),
            ],
        ];

        $verification = $this->verifyToken($apiKey, $accountId);

        if (is_wp_error($verification)) {
            $extraRows[] = [
                'title'   => __('Connection Status', 'fluent-smtp'),
                'content' => '<span style="color:#dc3232;">' . esc_html($verification->get_error_message()) . '</span>',
            ];
        } else {
            $extraRows[] = [
                'title'   => __('Connection Status', 'fluent-smtp'),
                'content' => '<span style="color:#46b450;">' . esc_html__('API Token is valid and active', 'fluent-smtp') . '</span>',
            ];
            if (!empty($verification['token_id'])) {
                $extraRows[] = [
                    'title'   => __('Token ID', 'fluent-smtp'),
                    'content' => esc_html($verification['token_id']),
                ];
            }
        }

        $connection['extra_rows'] = $extraRows;

        return [
            'info' => (string) fluentMail('view')->make('admin.general_connection_info', [
                'connection' => $connection
            ])
        ];
    }

    protected function verifyToken($apiKey, $accountId)
    {
        if (!$apiKey || !$accountId) {
            return new \WP_Error(422, __('API token and Account ID are required.', 'fluent-smtp'));
        }

        $response = wp_safe_remote_get(
            'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($accountId) . '/tokens/verify',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept'        => 'application/json',
                ],
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code != 200 || empty($body['success'])) {
            $message = Arr::get($body, 'errors.0.message', __('Invalid Cloudflare API token or Account ID.', 'fluent-smtp'));
            return new \WP_Error($code ?: 400, $message);
        }

        $status = Arr::get($body, 'result.status');
        if ($status !== 'active') {
            return new \WP_Error(400, sprintf(__('Cloudflare token is not active (status: %s).', 'fluent-smtp'), $status));
        }

        return [
            'token_id' => Arr::get($body, 'result.id', ''),
            'status'   => $status,
        ];
    }

    public function checkConnection($connection)
    {
        $this->setSettings($connection);

        $result = $this->verifyToken($this->getSetting('api_key'), $this->getSetting('account_id'));

        if (is_wp_error($result)) {
            $this->throwValidationException([
                'api_key' => [
                    'required' => $result->get_error_message()
                ]
            ]);
        }

        return true;
    }

    protected function getFrom()
    {
        $email = $this->getParam('sender_email');

        if ($name = $this->getParam('sender_name')) {
            return [
                'address' => $email,
                'name'    => $name,
            ];
        }

        return $email;
    }

    protected function getReplyTo()
    {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            $replyTo = reset($replyTo);
            return isset($replyTo['email']) ? $replyTo['email'] : null;
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
            $list[] = $recipient['email'];
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
                $fileName = basename($attachment[0]);
            } catch (\Exception $e) {
                error_log('FluentSMTP Cloudflare: Failed to read attachment - ' . $e->getMessage());
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
