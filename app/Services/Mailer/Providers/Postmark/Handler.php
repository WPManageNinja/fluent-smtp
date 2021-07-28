<?php

namespace FluentMail\App\Services\Mailer\Providers\Postmark;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $url = 'https://api.postmarkapp.com/email';

    public function send()
    {
        if ($this->preSend() && $this->phpMailer->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(423, 'Something went wrong!', []));
    }

    public function postSend()
    {
        $body = [
            'From'          => $this->getParam('from'),
            'To'            => $this->getTo(),
            'Subject'       => $this->getSubject(),
            'MessageStream' => $this->getSetting('message_stream', 'outbound')
        ];

        if ($replyTo = $this->getReplyTo()) {
            $body['ReplyTo'] = $replyTo;
        }

        if ($bcc = $this->getBlindCarbonCopy()) {
            $body['Bcc'] = $bcc;
        }

        if ($cc = $this->getCarbonCopy()) {
            $body['Cc'] = $cc;
        }

        if ($this->getHeader('content-type') == 'text/html') {
            $body['HtmlBody'] = $this->getParam('message');

            if ($this->getSetting('track_opens') == 'yes') {
                $body['TrackOpens'] = true;
            }

            if ($this->getSetting('track_links') == 'yes') {
                $body['TrackLinks'] = 'HtmlOnly';
            }

        } else {
            $body['TextBody'] = $this->getParam('message');
        }

        if (!empty($this->getParam('attachments'))) {
            $body['Attachments'] = $this->getAttachments();
        }

        // Handle apostrophes in email address From names by escaping them for the Postmark API.
        $from_regex = "/(\"From\": \"[a-zA-Z\\d]+)*[\\\\]{2,}'/";

        $args = array(
            'headers' => $this->getRequestHeaders(),
            'body'    => preg_replace($from_regex, "'", wp_json_encode($body), 1),
        );

        $response = wp_remote_post($this->url, $args);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = wp_remote_retrieve_body($response);
            $responseCode = wp_remote_retrieve_response_code($response);

            $isOKCode = $responseCode == $this->emailSentCode;

            $responseBody = \json_decode($responseBody, true);

            if ($isOKCode) {
                $returnResponse = [
                    'id'      => Arr::get($responseBody, 'MessageID'),
                    'message' => Arr::get($responseBody, 'Message')
                ];
            } else {
                $returnResponse = new \WP_Error($responseCode, Arr::get($responseBody, 'Message', 'Unknown Error'), $responseBody);
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if ($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_POSTMARK_API_KEY') ? FLUENTMAIL_POSTMARK_API_KEY : '';
        }

        $this->settings = $settings;
        return $this;
    }

    protected function getReplyTo()
    {
        if ($replyTo = $this->getParam('headers.reply-to')) {
            $replyTo = reset($replyTo);
            return $replyTo['email'];
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
        $array = array_map(function ($recipient) {
            return isset($recipient['name'])
                ? $recipient['name'] . ' <' . $recipient['email'] . '>'
                : $recipient['email'];
        }, $recipients);

        return implode(', ', $array);
    }

    protected function getAttachments()
    {
        $data = [];

        foreach ($this->getParam('attachments') as $attachment) {
            $file = false;

            try {
                if (is_file($attachment[0]) && is_readable($attachment[0])) {
                    $fileName = basename($attachment[0]);
                    $file = file_get_contents($attachment[0]);
                }
            } catch (\Exception $e) {
                $file = false;
            }

            if ($file === false) {
                continue;
            }

            $data[] = [
                'Name'        => $fileName,
                'Content'     => base64_encode($file),
                'ContentType' => $this->determineMimeContentRype($attachment[0])
            ];
        }

        return $data;
    }

    protected function getRequestHeaders()
    {
        return [
            'Accept'                  => 'application/json',
            'Content-Type'            => 'application/json',
            'X-Postmark-Server-Token' => $this->getSetting('api_key'),
        ];
    }

    protected function determineMimeContentRype($filename)
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filename);
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mime_type;
        } else {
            return 'application/octet-stream';
        }
    }
}
