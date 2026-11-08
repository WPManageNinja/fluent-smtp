<?php

namespace FluentMail\App\Services\Mailer\Providers\Mailgun;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $url = null;

    const API_BASE_US = 'https://api.mailgun.net/v3/';

    const API_BASE_EU = 'https://api.eu.mailgun.net/v3/';

    public function send()
    {
        if ($this->preSend()) {
            $this->setUrl();
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong.', 'fluent-smtp'), []));
    }

    protected function setUrl()
    {
        $url = $this->getSetting('region') == 'eu' ? self::API_BASE_EU : self::API_BASE_US;

        $url .= sanitize_text_field($this->getSetting('domain_name') . '/messages');

        return $this->url = $url;
    }

    public function postSend()
    {
        $content_type = $this->getHeader('content-type');
        $body = [
            'from'       => $this->getFrom(),
            'subject'    => $this->getSubject(),
            'h:X-Mailer' => 'FluentMail - Mailgun'
        ];

        if (strtolower($content_type) == 'multipart/alternative') {
            $body['html'] = $this->getBody();
            if ((string)$this->phpMailer->AltBody !== '') {
                $body['text'] = $this->phpMailer->AltBody;
            }
        } else if (stripos($content_type, 'html') !== false) {
            $body['html'] = $this->getBody();
        } else {
            $body['text'] = $this->getBody();
        }

        if ($replyTo = $this->getReplyTo()) {
            $body['h:Reply-To'] = $replyTo;
        }

        $recipients = [
            'to'  => $this->getTo(),
            'cc'  => $this->getCarbonCopy(),
            'bcc' => $this->getBlindCarbonCopy()
        ];

        if ($recipients = array_filter($recipients)) {
            $body = array_merge($body, $recipients);
        }

        foreach ($this->getParam('custom_headers') as $header) {
            $key = trim($header['key']);
            $body['h:' . $key] = trim($header['value']);
        }

        $params = [
            'body'    => $body,
            'headers' => $this->getRequestHeaders()
        ];

        $params = array_merge($params, $this->getDefaultParams());

        if (!empty($this->attributes['attachments'])) {
            $params = $this->getAttachments($params);
        }

        $response = wp_safe_remote_post($this->url, $params);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);
            $responseCode = wp_remote_retrieve_response_code($response);

            $isOKCode = $responseCode == $this->emailSentCode;

            if ($isOKCode) {
                $responseBody = \json_decode($responseBody, true);
            }

            if ($isOKCode && isset($responseBody['id'])) {
                $returnResponse = [
                    'id'      => Arr::get($responseBody, 'id'),
                    'message' => Arr::get($responseBody, 'message')
                ];
            } else {
                $returnResponse = new \WP_Error($responseCode, __('Mailgun API Error', 'fluent-smtp'), $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if ($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_MAILGUN_API_KEY') ? FLUENTMAIL_MAILGUN_API_KEY : '';
            $settings['domain_name'] = defined('FLUENTMAIL_MAILGUN_DOMAIN') ? FLUENTMAIL_MAILGUN_DOMAIN : '';
        }
        $this->settings = $settings;

        return $this;
    }

    protected function getFrom()
    {
        return $this->getFormattedFrom();
    }

    protected function getReplyTo()
    {
        return $this->getRecipients(
            $this->getParam('headers.reply-to')
        );
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
        $array = array_map(function ($recipient) {
            return isset($recipient['name'])
                ? self::formatAddress($recipient['email'], $recipient['name'])
                : $recipient['email'];
        }, $recipients);

        return implode(', ', $array);
    }

    protected function getBody()
    {
        return $this->getParam('message');
    }

    protected function getAttachments($params)
    {
        $data = [];
        $payload = '';
        $attachments = $this->attributes['attachments'];
        $contentIds = self::inlineContentIds($attachments);
        $html = isset($params['body']['html']) ? (string) $params['body']['html'] : '';

        // Mailgun uses an "inline" part's filename as its Content-ID and types the
        // part from that name, ignoring the type sent: an image under the cid
        // "abc@example.com" goes out as a Windows program and Gmail refuses it.
        // Such an image gets a name like inline-1.png and the HTML is pointed at it.
        $inlineNames = self::inlineNamesForUnsafeCids($attachments, $html);

        foreach ($attachments as $index => $attachment) {
            try {
                $file = self::attachmentContent($attachment);
            } catch (\Exception $e) {
                $this->logAttachmentFailure('Mailgun', $e);
                continue;
            }

            $contentId = $contentIds[$index];
            $name = self::attachmentName($attachment);
            $inline = $contentId !== '';

            if (isset($inlineNames[$index])) {
                $name = $inlineNames[$index]['name'];
            } elseif ($inline && self::isImageAttachment($attachment, $name) && !self::canNameByCid($contentId, $attachment, $name)) {
                // Not shown by a quoted src, so it cannot be renamed: it goes as a
                // normal attachment under its real name rather than be typed from the cid.
                $inline = false;
            } elseif ($inline) {
                $name = $contentId;
            }

            $data[] = [
                'content' => $file,
                'name'    => $name,
                'type'    => self::attachmentType($attachment),
                'inline'  => $inline,
            ];
        }

        if ($inlineNames) {
            $params['body']['html'] = self::pointHtmlAtInlineNames($html, $inlineNames, array_column($data, 'name'));
        }

        if (!empty($data)) {
            $boundary = hash('sha256', uniqid('', true));

            foreach ($params['body'] as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $child_key => $child_value) {
                        $payload .= '--' . $boundary;
                        $payload .= "\r\n";
                        $payload .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
                        $payload .= $child_value;
                        $payload .= "\r\n";
                    }
                } else {
                    $payload .= '--' . $boundary;
                    $payload .= "\r\n";
                    $payload .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
                    $payload .= $value;
                    $payload .= "\r\n";
                }
            }

            foreach ($data as $key => $attachment) {
                $payload .= '--' . $boundary;
                $payload .= "\r\n";
                // Inline parts use Mailgun's documented "inline" field; the type is
                // sent because a cid such as "logo" or "0" has no extension to go by.
                $field = $attachment['inline'] ? 'inline' : 'attachment[' . $key . ']';
                $payload .= 'Content-Disposition: form-data; name="' . $field . '"; filename="' . $attachment['name'] . '"' . "\r\n";
                $payload .= 'Content-Type: ' . $attachment['type'] . "\r\n\r\n";
                $payload .= $attachment['content'];
                $payload .= "\r\n";
            }

            $payload .= '--' . $boundary . '--';

            $params['body'] = $payload;

            $params['headers']['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
        }

        return $params;
    }

    protected function getRequestHeaders()
    {
        $apiKey = $this->getSetting('api_key');

        return [
            'Authorization' => 'Basic ' . base64_encode('api:' . $apiKey)
        ];
    }
}
