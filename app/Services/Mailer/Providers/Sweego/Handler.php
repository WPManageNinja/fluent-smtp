<?php

namespace FluentMail\App\Services\Mailer\Providers\Sweego;

use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $emailSentCode = 200;

    protected $url = 'https://api.sweego.io/send';

    /**
     * Sweego accepts at most five custom headers per message and rejects the
     * whole request beyond that. Dropping the extras keeps the email going out.
     */
    const MAX_CUSTOM_HEADERS = 5;

    public function send()
    {
        if ($this->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong.', 'fluent-smtp'), []));
    }

    public function postSend()
    {
        $body = [
            'channel'    => 'email',
            'provider'   => 'sweego',
            'from'       => $this->getFrom(),
            'recipients' => $this->getTo(),
            'subject'    => $this->getSubject()
        ];

        $contentType = $this->getHeader('content-type');

        if ($contentType == 'text/html') {
            $body['message-html'] = $this->getParam('message');
        } elseif ($contentType == 'multipart/alternative') {
            $body['message-html'] = $this->getParam('message');
            $body['message-txt'] = $this->phpMailer->AltBody;
        } else {
            $body['message-txt'] = $this->getParam('message');
        }

        /*
         * Cc and Bcc travel in their own lists. Merged into `recipients` - as an
         * earlier revision of this handler did - every address would be visible
         * in the To line, which for a Bcc is exactly what must not happen.
         */
        if ($cc = $this->getCarbonCopy()) {
            $body['cc'] = $cc;
        }

        if ($bcc = $this->getBlindCarbonCopy()) {
            $body['bcc'] = $bcc;
        }

        if ($replyTo = $this->getReplyTo()) {
            $body['reply-to'] = $replyTo;
        }

        if ($headers = $this->getCustomEmailHeaders()) {
            $body['headers'] = $headers;
        }

        if (!empty($this->getParam('attachments'))) {
            $body['attachments'] = $this->getAttachments();
        }

        $params = array_merge([
            'headers' => $this->getRequestHeaders(),
            'body'    => wp_json_encode($body)
        ], $this->getDefaultParams());

        $response = wp_safe_remote_post($this->url, $params);

        if (is_wp_error($response)) {
            $returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
        } else {
            $responseBody = \json_decode(wp_remote_retrieve_body($response), true);
            $responseCode = wp_remote_retrieve_response_code($response);

            if ($responseCode == $this->emailSentCode) {
                // Sweego answers with the tracking id it gave each recipient; keep
                // whatever it sent alongside our own line for the log viewer.
                $returnResponse = array_merge(
                    ['message' => __('Email sent successfully via Sweego', 'fluent-smtp')],
                    is_array($responseBody) ? $responseBody : []
                );
            } else {
                $returnResponse = new \WP_Error(
                    $responseCode ?: 400,
                    $this->getErrorMessage($responseBody),
                    $responseBody
                );
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    /**
     * The failure to show for a rejected send.
     *
     * A validation error names the field it rejected, which is the difference
     * between "your From address is not on a verified domain" and a bare 400.
     *
     * @param mixed $responseBody The decoded response, or null when it was not JSON.
     * @return string
     */
    protected function getErrorMessage($responseBody)
    {
        if (!is_array($responseBody)) {
            return __('Unknown Error', 'fluent-smtp');
        }

        foreach (['error', 'message', 'detail', 'errors.0.message', 'errors.0'] as $key) {
            $message = Arr::get($responseBody, $key);

            if ($message && is_string($message)) {
                return $message;
            }
        }

        return __('Unknown Error', 'fluent-smtp');
    }

    public function setSettings($settings)
    {
        if (Arr::get($settings, 'key_store') == 'wp_config') {
            $settings['api_key'] = defined('FLUENTMAIL_SWEEGO_API_KEY') ? FLUENTMAIL_SWEEGO_API_KEY : '';
        }

        $this->settings = $settings;

        return $this;
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
        $replyTo = $this->getParam('headers.reply-to');

        if (!$replyTo) {
            return null;
        }

        // Sweego takes a single address here; wp_mail() may have collected several.
        $replyTo = reset($replyTo);

        if (empty($replyTo['email'])) {
            return null;
        }

        $address = ['email' => $replyTo['email']];

        if (!empty($replyTo['name'])) {
            $address['name'] = $replyTo['name'];
        }

        return $address;
    }

    protected function getTo()
    {
        return $this->formatRecipients($this->getParam('to'));
    }

    protected function getCarbonCopy()
    {
        return $this->formatRecipients($this->getHeader('cc'));
    }

    protected function getBlindCarbonCopy()
    {
        return $this->formatRecipients($this->getHeader('bcc'));
    }

    /**
     * Sweego keeps the address and the display name apart, so a name holding a
     * comma or a quote needs no escaping and cannot split into two recipients.
     *
     * @param array|null $recipients
     * @return array
     */
    protected function formatRecipients($recipients)
    {
        $formatted = [];

        if (empty($recipients)) {
            return $formatted;
        }

        foreach ($recipients as $recipient) {
            if (empty($recipient['email'])) {
                continue;
            }

            $address = ['email' => $recipient['email']];

            if (!empty($recipient['name'])) {
                $address['name'] = $recipient['name'];
            }

            $formatted[] = $address;
        }

        return $formatted;
    }

    protected function getCustomEmailHeaders()
    {
        $headers = [];

        foreach ((array)$this->getParam('custom_headers') as $header) {
            if (empty($header['key'])) {
                continue;
            }

            $headers[$header['key']] = (string)Arr::get($header, 'value', '');

            if (count($headers) == self::MAX_CUSTOM_HEADERS) {
                break;
            }
        }

        return $headers;
    }

    protected function getAttachments()
    {
        $data = [];

        foreach ($this->getParam('attachments') as $attachment) {
            try {
                /*
                 * An attachment built in memory - PHPMailer's
                 * addStringAttachment(), which plugins reach through the
                 * phpmailer_init hook - holds its data at index 0 rather than a
                 * path, and says so at index 5. Reading it as a path would
                 * throw, and the whole email would go out without it.
                 *
                 * Once #450 lands this is `self::attachmentContent($attachment)`.
                 */
                $file = !empty($attachment[5])
                    ? (string)$attachment[0]
                    : $this->secureFileRead($attachment[0]);
            } catch (\Exception $e) {
                $this->logAttachmentFailure('Sweego', $e);
                continue;
            }

            /*
             * Sweego reads the type from the file name, which is why the name
             * matters here: an attachment with none arrives as an unnamed blob.
             */
            $data[] = [
                'filename' => $this->getAttachmentName($attachment),
                'content'  => base64_encode($file)
            ];
        }

        return $data;
    }

    protected function getRequestHeaders()
    {
        return [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'Api-Key'      => $this->getSetting('api_key')
        ];
    }
}
