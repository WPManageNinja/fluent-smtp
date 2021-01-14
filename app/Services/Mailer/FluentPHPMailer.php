<?php

namespace FluentMail\App\Services\Mailer;

use FluentMail\App\Models\Logger;
use FluentMail\App\Services\Mailer\Providers\DefaultMail\Handler as PHPMailer;
use FluentMail\App\Services\Mailer\Providers\Factory;

class FluentPHPMailer
{
    protected $app = null;

    protected $phpMailer = null;

    public function __construct($phpMailer)
    {
        $this->app = fluentMail();
        $this->phpMailer = $phpMailer;
    }

    public static function __callStatic($method, $params)
    {
        return call_user_func_array([$this->phpMailer, $method], $params);
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->phpMailer, $method], $params);
    }

    public function __get($key)
    {
        return $this->phpMailer->{$key};
    }

    public function __set($key, $value)
    {
        $this->phpMailer->{$key} = $value;
    }

    public function send()
    {
        $fromEmail = $this->phpMailer->From;

        $driver = fluentGetMailDriver($fromEmail);

        if ($driver) {
            if($forceFromEmail = $driver->getSetting('force_from_email_id')) {
                $this->phpMailer->From = $forceFromEmail;
            }

            return $driver->setPhpMailer($this->phpMailer)->send();
        }

        return $this->phpMailer->send();
    }

    public function _send()
    {
        try {
            return $this->enqueueEmail();
        } catch (\InvalidArgumentException $e) {
            // There's no providers available
            return $this->phpMailer->send();
        }
    }

    public function enqueueEmail()
    {
        $provider = $this->app->applyCustomFilters(
            'active_driver', $this->phpMailer
        );

        $customHeaders = $this->getCustomEmailHeaders();

        $sender = $this->getSender($customHeaders);

        $data = [
            'status'  => Logger::STATUS_PENDING,
            'subject' => $this->phpMailer->Subject,
            'from'    => $this->getFrom(),
            'to'      => $this->getTo(),
            'body'    => [
                'html' => $this->phpMailer->Body,
                'text' => $this->phpMailer->AltBody
            ],
            'headers' => [
                'cc'           => $this->getCc(),
                'bcc'          => $this->getBcc(),
                'reply-to'     => $this->getReplyTo(),
                'sender'       => $sender,
                'content-type' => $this->phpMailer->ContentType
            ]
        ];

        if ($attachments = $this->phpMailer->getAttachments()) {
            $data['attachments'] = maybe_serialize(
                $this->phpMailer->getAttachments()
            );
        }

        if ($customHeaders) {
            $data['extra'] = maybe_serialize([
                'provider'       => '',
                'custom_headers' => $customHeaders
            ]);
        }

        return $provider->send($data);

        // return (new Logger)->add($data);
    }

    protected function getCustomEmailHeaders()
    {
        $customHeaders = [];

        foreach ($this->phpMailer->getCustomHeaders() as $header) {
            if ($header[0] == 'Return-Path' || strpos($header[0], 'X-') !== false) {
                $customHeaders[$header[0]] = $header[1];
            }
        }
        return $customHeaders;
    }

    protected function getSender($customHeaders)
    {
        $sender = $this->phpMailer->Sender;

        if (!$sender && isset($customHeaders['Return-Path'])) {
            $sender = $customHeaders['Return-Path'];
        }

        return $sender;
    }

    protected function getFrom()
    {
        $from = [
            'email' => $this->phpMailer->From
        ];

        if (isset($this->phpMailer->FromName)) {
            $from['name'] = $this->phpMailer->FromName;
        }

        return [$from];
    }

    protected function getTo()
    {
        return $this->setRecipientsArray(
            $this->phpMailer->getToAddresses()
        );
    }

    protected function getCc()
    {
        return $this->setRecipientsArray(
            $this->phpMailer->getCcAddresses()
        );
    }

    protected function getBcc()
    {
        return $this->setRecipientsArray(
            $this->phpMailer->getBccAddresses()
        );
    }

    protected function getReplyTo()
    {
        return $this->setRecipientsArray(
            array_values($this->phpMailer->getReplyToAddresses())
        );
    }

    protected function setRecipientsArray(array $data)
    {
        $recipients = [];

        foreach ($data as $key => $recipient) {
            $recipient = array_filter($recipient);

            if (!$recipient) continue;

            $recipients[$key] = [
                'email' => array_shift($recipient)
            ];

            if ($name = array_shift($recipient)) {
                $recipients[$key]['name'] = $name;
            }
        }

        return $recipients;
    }
}
