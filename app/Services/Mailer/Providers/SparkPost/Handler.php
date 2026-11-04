<?php

namespace FluentMail\App\Services\Mailer\Providers\SparkPost;

use WP_Error as WPError;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\SparkPost\ValidatorTrait;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 202;

    protected $url = 'https://api.sparkpost.com/api/v1/transmissions';

    public function send()
    {   
        if ($this->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong.', 'fluent-smtp'), []) );
    }

    public function postSend()
    {
        $body = [
            'options' => [
                'sandbox' => false
            ],
            'content' => [
                'from' => $this->getFrom(),
                'subject' => $this->getSubject(),
                'html' => $this->phpMailer->Body,
                'text' => $this->phpMailer->AltBody,
                'headers' => (object) []
            ],
            'recipients' => $this->getTo(),
            'cc' => $this->getCarbonCopy(),
            'bcc' => $this->getBlindCarbonCopy()
        ];

        if ($this->getParam('headers.content-type') == 'text/plain') {
            $body['content']['text'] = $this->phpMailer->Body;
            unset($body['content']['html']);
        }

        if ($replyTo = $this->getReplyTo()) {
            $body['content']['reply_to'] = $replyTo;
        }

        if (!empty($this->getParam('attachments'))) {
            if ($attachments = $this->getAttachments()) {
                $body['content']['attachments'] = $attachments;
            }

            // SparkPost puts an inline image's name in its Content-ID header,
            // so embedded images go in inline_images under their cid.
            if ($inlineImages = $this->attachmentRows(true)) {
                $body['content']['inline_images'] = $inlineImages;
            }
        }

        $params = [
            'body' => json_encode($body),
            'headers' => $this->getRequestHeaders()
        ];

        $params = array_merge($params, $this->getDefaultParams());

        $response = wp_safe_remote_post($this->url, $params);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);
            $responseCode = wp_remote_retrieve_response_code($response);

            $isOKCode = $responseCode < 300;

            $responseBody = \json_decode($responseBody, true);

            if($isOKCode) {
                $returnResponse = [
                    'response' => $responseBody
                ];
            } else {
                $returnResponse = new \WP_Error($responseCode, __('SparkPost API Error', 'fluent-smtp'), $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_SPARKPOST_API_KEY') ? FLUENTMAIL_SPARKPOST_API_KEY : '';
        }
        $this->settings = $settings;
        return $this;
    }

    protected function getFrom()
    {
        $email = $this->getParam('sender_email');

        if ($name = $this->getParam('sender_name')) {
            $from = self::formatAddress($email, $name);
        } else {
            $from = $email;
        }

        return $from;
    }

    protected function getTo()
    {
        $address = [];

        foreach ($this->getParam('to') as $to) {
            $address[] = [
                'address' => [
                    'email' => $to['email']
                ]
            ];
        }

        return $address;
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

    protected function getCarbonCopy()
    {
        $address = [];

        foreach ($this->getParam('headers.cc') as $cc) {
            $address[] = [
                'address' => [
                    'email' => $cc['email']
                ]
            ];
        }

        return $address;
    }

    protected function getBlindCarbonCopy()
    {
        $address = [];

        foreach ($this->getParam('headers.bcc') as $bcc) {
            $address[] = [
                'address' => [
                    'email' => $bcc['email']
                ]
            ];
        }

        return $address;
    }

    /**
     * The normal attachments; embedded images are sent separately (attachmentRows(true)).
     *
     * @return array
     */
    protected function getAttachments()
    {
        return $this->attachmentRows(false);
    }

    /**
     * @param bool $inline True for the embedded images, false for normal attachments.
     * @return array
     */
    protected function attachmentRows($inline)
    {
        $data = [];

        $attachments = $this->getParam('attachments');
        $contentIds = self::inlineContentIds($attachments);

        foreach ($attachments as $key => $attachment) {
            $contentId = $contentIds[$key];

            if (($contentId !== '') !== $inline) {
                continue;
            }

            try {
                $file = self::attachmentContent($attachment);
            } catch (\Exception $e) {
                $this->logAttachmentFailure('SparkPost', $e);
                continue;
            }

            // SparkPost's attachment object is name, type and data (all required).
            $data[] = [
                'name' => $inline ? $contentId : self::attachmentName($attachment),
                'type' => self::attachmentType($attachment),
                'data' => base64_encode($file)
            ];
        }

        return $data;
    }

    protected function getCustomEmailHeaders()
    {
        return [
            'X-Mailer' => 'FluentMail - SparkPost'
        ];
    }

    protected function getRequestHeaders()
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => $this->getSetting('api_key')
        ];
    }
}
