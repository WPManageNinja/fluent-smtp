<?php

namespace FluentMail\App\Services\Mailer\Providers\AmazonSes;

use WP_Error as WPError;
use FluentMail\App\Models\Settings;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\ValidatorTrait;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailService;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailServiceMessage;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $client = null;

    const RAW_REQUEST = true;

    const TRIGGER_ERROR = false;

    public function send()
    {
        if ($this->preSend()) {
            $this->client = new SimpleEmailServiceMessage;
            return $this->postSend();
        }

        $this->handleFailure(new Exception('Something went wrong!', 0));
    }

    public function postSend()
    {

        list($text, $html) = $this->getBody();

        $this->client->setFrom($this->getFrom());
        $this->client->addReplyTo($this->getReplyTo());
        $this->client->addTo($this->getTo());
        $this->client->addCC($this->getCarbonCopy());
        $this->client->addBCC($this->getBlindCarbonCopy());
        $this->client->setSubject($this->getSubject());

        if ($this->phpMailer->ContentType == 'text/plain') {
            if (!$text) {
                $text = $html;
            }
            $this->client->setMessageFromString($text);
        } else {
            $this->client->setMessageFromString($text, $html);
        }

        if (!empty($this->getParam('attachments'))) {
            foreach ($this->getAttachments() as $attachment) {
                $this->client->addAttachmentFromData(
                    $attachment['name'], $attachment['content'], $attachment['type']
                );
            }
        }

        foreach ($this->getCustomEmailHeaders() as $header) {
            $this->client->addCustomHeader($header);
        }

        $connectionSettings = $this->filterConnectionVars($this->getSetting());

        $ses = fluentMailSesConnection($connectionSettings);

        $this->response = $ses->sendEmail($this->client, static::RAW_REQUEST);

        return $this->handleResponse($this->response);
    }

    protected function getFrom()
    {
        return $this->getParam('from');
    }

    public function getVerifiedEmails()
    {
        return (new Settings)->getVerifiedEmails();
    }

    protected function getReplyTo()
    {
        $replyTo = $this->getRecipients(
            $this->getParam('headers.reply-to')
        );

        if (is_array($replyTo)) {
            $replyTo = reset($replyTo);
        }

        return $replyTo;
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
        $array = array_map(function($recipient) {
            return isset($recipient['name'])
            ? $recipient['name'] . ' <' . $recipient['email'] . '>'
            : $recipient['email'];
       }, $recipients);

        return implode(', ', $array);
    }

    protected function getBody()
    {
        return [
            $this->phpMailer->AltBody,
            $this->phpMailer->Body
        ];
    }

    protected function getAttachments()
    {
        $attachments = [];

        foreach ($this->getParam('attachments') as $attachment) {
            $file = false;

            try {
                if (is_file($attachment[0]) && is_readable($attachment[0])) {
                    $fileName = basename($attachment[0]);
                    $file = file_get_contents($attachment[0]);
                    $mimeType = mime_content_type($attachment[0]);
                    $filetype = str_replace(';', '', trim($mimeType));
                }
            } catch (\Exception $e) {
                $file = false;
            }

            if ($file === false) {
                continue;
            }

            $attachments[] = [
                'type' => $filetype,
                'name' => $fileName,
                'content' => $file
            ];
        }

        return $attachments;
    }

    protected function getCustomEmailHeaders()
    {
        $customHeaders = [
            'X-Mailer' => 'Amazon-SES'
        ];

        $headers = [];
        foreach ($customHeaders as $key => $header) {
            $headers[] = $key . ':' . $header;
        }

        return $headers;
    }

    protected function getRegion()
    {
        return 'email.' . $this->getSetting('region') . '.amazonaws.com';
    }

    protected function isEmailSent()
    {
        $isSent = false;

        if (is_array($this->response)) {
            $isSent = array_key_exists(
                'MessageId', $this->response
            ) && array_key_exists(
                'RequestId', $this->response
            );
        }

        return $isSent;
    }

    public function handleSuccess()
    {
        return $this->processResponse([
            'response' => $this->response
        ], true);
    }

    public function handleFailure()
    {
        $response = $this->getResponseError();

        $this->processResponse(['response' => $response], false);

        $this->fireWPMailFailedAction($response);
    }

    protected function getResponseError()
    {
        $response = (array) $this->response;
        
        if (!($response = array_filter($response))) {
            return [
                'errors' => ['Unknown Error'],
                'code' => 400,
                'message' => 'Something went wrong.'
            ];
        }

        $error = $response['error'];

        if (isset($error['Error'])) {
            $error = $error['Error'];
        }

        if (isset($error['Type'])) {
            $errors[] = 'Type: ' . $error['Type'];
        }

        if (isset($response['error']['RequestId'])) {
            $errors[] = 'Request-ID: ' . $response['error']['RequestId'];
        }

        if (isset($error['Message'])) {
            $errors[] = $error['Message'];
        }

        if (isset($error['message'])) {
            $errors[] = $error['message'];
        }

        return [
            'errors' => $errors,
            'code' => $response['code'],
            'message' => $error['Code']
        ];
    }

    public function getValidSenders($config)
    {
        $config = $this->filterConnectionVars($config);

        $region = 'email.' . $config['region'] . '.amazonaws.com';

        $ses = new SimpleEmailService(
            $config['access_key'],
            $config['secret_key'],
            $region,
            static::TRIGGER_ERROR
        );

        $validSenders = $ses->listVerifiedEmailAddresses();

        if($validSenders && isset($validSenders['Addresses'])) {
            return $validSenders['Addresses'];
        }

        return [];
    }

    public function getConnectionInfo($connection)
    {
        $connection = $this->filterConnectionVars($connection);

        $validSenders = $this->getValidSenders($connection);
        $stats = $this->getStats($connection);
        return (string) fluentMail('view')->make('admin.ses_connection_info', [
            'connection' => $connection,
            'valid_senders' => $validSenders,
            'stats' => $stats
        ]);
    }

    private function getStats($config)
    {
        $region = 'email.' . $config['region'] . '.amazonaws.com';

        $ses = new SimpleEmailService(
            $config['access_key'],
            $config['secret_key'],
            $region,
            static::TRIGGER_ERROR
        );
        return $ses->getSendQuota();
    }

    private function filterConnectionVars($connection)
    {
        if($connection['key_store'] == 'wp_config') {
            $connection['access_key'] = defined('FLUENTMAIL_AWS_ACCESS_KEY_ID') ? FLUENTMAIL_AWS_ACCESS_KEY_ID : '';
            $connection['secret_key'] = defined('FLUENTMAIL_AWS_SECRET_ACCESS_KEY') ? FLUENTMAIL_AWS_SECRET_ACCESS_KEY : '';
        }

        return $connection;
    }
}