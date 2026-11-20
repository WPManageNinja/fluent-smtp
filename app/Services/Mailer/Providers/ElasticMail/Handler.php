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

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong.', 'fluent-smtp'), []));
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
        } else if ($this->phpMailer->ContentType == 'multipart/alternative') {
            $postData['bodyHtml'] = $this->getBody();
            $postData['bodyText'] = $this->phpMailer->AltBody;
        } else {
            $postData['bodyText'] = $this->getBody();
        }

        foreach ($this->getParam('custom_headers') as $header) {
            $key = trim($header['key']);
            $postData['headers_' . $key] = $key . ': ' . trim($header['value']);
        }

        $parts = $this->attachmentParts($postData['bodyHtml']);
        $postData['bodyHtml'] = $parts['html'];

        $this->parseAllPostData($postData);
        $this->setAttachments($parts['files']);
        unset($parts); // the request body now holds the file contents
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

    /**
     * The attachments as they go to Elastic Email, read first so the HTML is only
     * pointed at images really sent. Elastic Email has no inline field, but embeds
     * an attachment whose file name is a src's cid (undocumented, seen in live
     * sends). An image whose cid can be its name keeps it; one whose cid cannot
     * gets a generated name and the request's HTML is pointed at it; anything
     * else keeps its real name.
     *
     * @param string $html The HTML being sent, '' for a plain-text email.
     * @return array ['html' => string, 'files' => array of [name, type, content, index]]
     */
    protected function attachmentParts($html)
    {
        $attachments = (array) $this->getParam('attachments');
        $files = [];

        if (!$attachments) {
            return ['html' => $html, 'files' => $files];
        }

        $contentIds = self::inlineContentIds($attachments);
        $inlineNames = self::inlineNamesForUnsafeCids($attachments, $html);

        foreach ($attachments as $i => $attachment) {
            if (empty($attachment) === true) {
                continue;
            }

            try {
                $content = self::attachmentContent($attachment);
            } catch (\Exception $e) {
                $this->logAttachmentFailure('ElasticMail', $e);
                continue;
            }

            $name = self::attachmentName($attachment);

            if (isset($inlineNames[$i])) {
                $name = $inlineNames[$i]['name'];
            } elseif (self::canNameByCid($contentIds[$i], $attachment, $name) && self::isShownBySrc($contentIds[$i], $html)) {
                $name = $contentIds[$i];
            }

            $files[] = ['name' => $name, 'type' => self::attachmentType($attachment), 'content' => $content, 'index' => $i];
        }

        if ($inlineNames) {
            $html = self::pointHtmlAtInlineNames($html, $inlineNames, array_column($files, 'name'));
        }

        return ['html' => $html, 'files' => $files];
    }

    /**
     * Whether a src uses this cid, quoted or not.
     *
     * @param string $cid
     * @param string $html
     * @return bool
     */
    protected static function isShownBySrc($cid, $html)
    {
        return $cid !== '' && (bool) preg_match('/\ssrc\s*=\s*["\']?cid:(?-i:' . preg_quote($cid, '/') . ')["\'\s>\/]/i', $html);
    }

    /**
     * Writes the prepared attachment parts into the request body.
     *
     * @param array $files From attachmentParts().
     */
    protected function setAttachments($files = [])
    {
        foreach ($files as $file) {
            $this->postbody[] = '--' . $this->boundary . "\r\n";
            $this->postbody[] = 'Content-Disposition: form-data; name="attachments' . ($file['index'] + 1) . '"; filename="' . $file['name'] . '"' . "\r\n";
            $this->postbody[] = 'Content-Type: ' . $file['type'] . "\r\n";
            $this->postbody[] = "\r\n";
            $this->postbody[] = $file['content'] . "\r\n";
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
            $error = __('API Key is invalid', 'fluent-smtp');
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
