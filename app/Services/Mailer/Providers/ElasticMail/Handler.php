<?php

namespace FluentMail\App\Services\Mailer\Providers\ElasticMail;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $boundary = '';

    protected $postbody = [];

    protected $url = 'https://api.elasticemail.com/v2/';

    public function send()
    {
        if ($this->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(423, 'Something went wrong!', []));
    }

    public function postSend()
    {
        $this->boundary = hash('sha256', uniqid('', true));
        $this->postbody = [];

        $replyTo = $this->getReplyTo();

        $postData = [
            'subject'         => $this->getSubject(),
            'from'            => $this->getParam('sender_email'),
            'fromName'        => $this->getParam('sender_name'),
            'replyTo'         => Arr::get($replyTo, 'email'),
            'replyToName'     => Arr::get($replyTo, 'name'),
            'msgTo'           => $this->getToFormatted(),
            'msgCC'           => $this->getCcFormatted(), // with ; separated or null
            'msgBcc'          => $this->getBccFormatted(), // with ; separated or null
            'bodyHtml'        => '',
            'bodyText'        => '',
            'charset'         => $this->phpMailer->CharSet,
            'encodingType'    => 0,
            'isTransactional' => ($this->getSetting('mail_type') == 'transactional') ? true : false
        ];

        if ($this->phpMailer->ContentType == 'text/html') {
            $postData['bodyHtml'] = $this->getBody();
        } else {
            $postData['bodyText'] = $this->getBody();
        }

        foreach ($this->getParam('custom_headers') as $header) {
            $key = trim($header['key']);
            $postData['headers_' . $key] = $key . ': ' . trim($header['value']);
        }

        $this->parseAllPostData($postData);
        $this->setAttachments();
        $this->postbody[] = '--' . $this->boundary . '--';

        $actualData = implode('', $this->postbody);

        try {
            $response = wp_remote_post($this->url . 'email/send?apikey=' . $this->getSetting('api_key'), array(
                    'method'  => 'POST',
                    'headers' => array(
                        'content-type' => 'multipart/form-data; boundary=' . $this->boundary
                    ),
                    'body'    => $actualData
                )
            );

            if (is_wp_error($response)) {
                $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
            } else {
                $responseBody = wp_remote_retrieve_body($response);
                $responseBody = \json_decode($responseBody, true);

                if (!$responseBody['success']) {
                    $returnResponse = new \WP_Error('api_error', $responseBody['error'], $responseBody);
                } else {
                    $returnResponse = [
                        'code'    => 200,
                        'message' => $responseBody
                    ];
                }
            }
        } catch (\Exception $exception) {
            $returnResponse = new \WP_Error($exception->getCode(), $exception->getMessage(), []);
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    protected function parseAllPostData($data)
    {
        foreach ($data as $key => $item) {
            if (empty($item)) {
                continue;
            }
            if (is_array($item)) {
                $this->parseAllPostData($item);
            } else {
                $this->postbody[] = '--' . $this->boundary . "\r\n" . 'Content-Disposition: form-data; name=' . $key . "\r\n\r\n" . $item . "\r\n";
            }
        }
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
        return [
            'name'  => '',
            'email' => ''
        ];
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

    protected function getCcFormatted()
    {
        $ccs = $this->getCarbonCopy();
        if (!$ccs) {
            return null;
        }

        $ccs = array_filter($ccs);

        $toFormatted = [];
        foreach ($ccs as $toEmail) {
            if (!empty($toEmail['name'])) {
                $string = $toEmail['name'] . ' <' . $toEmail['email'] . '>';
            } else {
                $string = $toEmail['email'];
            }
            $toFormatted[] = $string;
        }

        $toFormatted = array_filter($toFormatted);

        return implode(';', $toFormatted);
    }

    protected function getBccFormatted()
    {
        $ccs = $this->getBlindCarbonCopy();
        if (!$ccs) {
            return null;
        }

        $ccs = array_filter($ccs);

        $toFormatted = [];
        foreach ($ccs as $toEmail) {
            if (!empty($toEmail['name'])) {
                $string = $toEmail['name'] . ' <' . $toEmail['email'] . '>';
            } else {
                $string = $toEmail['email'];
            }
            $toFormatted[] = $string;
        }

        $toFormatted = array_filter($toFormatted);

        return implode(';', $toFormatted);
    }

    protected function getToFormatted()
    {
        $to = $this->getParam('to');
        $toFormatted = [];

        foreach ($to as $toEmail) {
            if (!empty($toEmail['name'])) {
                $string = $toEmail['name'] . ' <' . $toEmail['email'] . '>';
            } else {
                $string = $toEmail['email'];
            }
            $toFormatted[] = $string;
        }

        $toFormatted = array_filter($toFormatted);

        return implode(';', $toFormatted);
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
        return $this->getParam('message');
    }

    protected function setAttachments()
    {
        $rawAttachments = $this->getParam('attachments');

        if (empty($rawAttachments) === true) {
            return false;
        }

        foreach ($rawAttachments as $i => $attpath) {
            if (empty($attpath) === true) {
                continue;
            }

            if (!is_file($attpath[0]) || !is_readable($attpath[0])) {
                continue;
            }

            //Extracting the file name
            $filenameonly = explode("/", $attpath);
            $fname = $filenameonly[count($filenameonly) - 1];

            $this->postbody[] = '--' . $this->boundary . "\r\n";
            $this->postbody[] = '--' . 'Content-Disposition: form-data; name="attachments' . ($i + 1) . '"; filename="' . $fname . '"' . "\r\n\r\n";

            //Loading attachment
            $handle = fopen($attpath, "r");
            if ($handle) {
                $fileContent = '';
                while (($buffer = fgets($handle, 4096)) !== false) {
                    $fileContent .= $buffer;
                }
                fclose($handle);

                $this->postbody[] = $fileContent . "\r\n";
            }
        }
    }

    protected function getCustomEmailHeaders()
    {
        return [];
    }

    protected function getRequestHeaders()
    {
        return [
            'Content-Type'          => 'application/json',
            'X-ElasticEmail-ApiKey' => $this->getSetting('api_key')
        ];
    }

    public function setSettings($settings)
    {
        if ($settings['key_store'] == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_ELASTICMAIL_API_KEY') ? FLUENTMAIL_ELASTICMAIL_API_KEY : '';
        }
        $this->settings = $settings;
        return $this;
    }

    public function checkConnection($connection)
    {
        $this->setSettings($connection);
        $request = wp_remote_get($this->url . 'account/profileoverview', [
            'body' => [
                'apikey' => $this->getSetting('api_key')
            ]
        ]);

        if (is_wp_error($request)) {
            $this->throwValidationException([
                'api_key' => [
                    'required' => $request->get_error_message()
                ]
            ]);
        }

        $response = json_decode(wp_remote_retrieve_body($request), true);

        if (!$response || empty($response['success'])) {
            $error = 'API Key is invalid';
            if (!empty($response['error'])) {
                $error = $response['error'];
            }

            $this->throwValidationException([
                'api_key' => [
                    'required' => $error
                ]
            ]);
        }

        return true;
    }
}
