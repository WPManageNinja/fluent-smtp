<?php

namespace FluentMail\App\Services\Mailer\Providers\Smtp;

use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\Smtp\ValidatorTrait;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    public function send()
    {
        if ($this->preSend()) {
            if ($this->getSetting('auto_tls') == 'no') {
                $this->phpMailer->SMTPAutoTLS = false;
            }
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(423, 'Something went wrong!', []) );
    }

    protected function postSend()
    {
        try {
            $this->phpMailer->isSMTP();
            $this->phpMailer->Host = $this->getSetting('host');
            $this->phpMailer->Port = $this->getSetting('port');

            if ($this->getSetting('auth') == 'yes') {
                $this->phpMailer->SMTPAuth = true;
                $this->phpMailer->Username = $this->getSetting('username');
                $this->phpMailer->Password = $this->getSetting('password');
            }

            if (($encryption = $this->getSetting('encryption')) != 'none') {
                $this->phpMailer->SMTPSecure = $encryption;
            }

            $fromEmail = $this->phpMailer->From;

            if ($this->isForcedEmail() && !fluentMailIsListedSenderEmail($fromEmail)) {
                $fromEmail = $this->getSetting('sender_email');
            }

            if (isset($this->phpMailer->FromName)) {
                $fromName = $this->phpMailer->FromName;

                if (
                    $this->getSetting('force_from_name') == 'yes' &&
                    $customFrom = $this->getSetting('sender_name')
                ) {
                    $fromName = $customFrom;
                }

                $this->phpMailer->setFrom($fromEmail, $fromName);
            } else {
                $this->phpMailer->setFrom($fromEmail);
            }

            foreach ($this->getParam('to') as $to) {
                if (isset($to['name'])) {
                    $this->phpMailer->addAddress($to['email'], $to['name']);
                } else {
                    $this->phpMailer->addAddress($to['email']);
                }
            }

            foreach ($this->getParam('headers.reply-to') as $replyTo) {
                if (isset($replyTo['name'])) {
                    $this->phpMailer->addReplyTo($replyTo['email'], $replyTo['name']);
                } else {
                    $this->phpMailer->addReplyTo($replyTo['email']);
                }
            }

            foreach ($this->getParam('headers.cc') as $cc) {
                if (isset($cc['name'])) {
                    $this->phpMailer->addCC($cc['email'], $cc['name']);
                } else {
                    $this->phpMailer->addCC($cc['email']);
                }
            }

            foreach ($this->getParam('headers.bcc') as $bcc) {
                if (isset($bcc['name'])) {
                    $this->phpMailer->addBCC($bcc['email'], $bcc['name']);
                } else {
                    $this->phpMailer->addBCC($bcc['email']);
                }
            }

            if ($attachments = $this->getParam('attachments')) {
                foreach ($attachments as $attachment) {
                    $this->phpMailer->addAttachment($attachment[0], $attachment[7]);
                }
            }

            if ($this->getParam('headers.content-type') == 'text/html') {
                $this->phpMailer->isHTML(true);
            }

            $this->phpMailer->Subject = $this->getSubject();
            
            $this->phpMailer->Body = $this->getParam('message');

            $this->phpMailer->send();

            $returnResponse = [
                'response' => 'OK'
            ];

        } catch(\Exception $e) {
            $returnResponse = new \WP_Error(423, $e->getMessage(), []);
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        if (Arr::get($settings, 'key_store') == 'wp_config') {
            $settings['username'] = defined('FLUENTMAIL_SMTP_USERNAME') ? FLUENTMAIL_SMTP_USERNAME : '';
            $settings['password'] = defined('FLUENTMAIL_SMTP_PASSWORD') ? FLUENTMAIL_SMTP_PASSWORD : '';
        }

        $this->settings = $settings;

        return $this;
    }
}
