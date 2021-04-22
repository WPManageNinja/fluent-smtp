<?php

namespace FluentMail\App\Services\Mailer\Providers\Simulator;

use FluentMail\App\Models\Logger;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    public function send()
    {
        if($this->shouldBeLogged(true)) {
            $this->setAttributes();
            $logData = [
                'to' => maybe_serialize($this->setRecipientsArray($this->phpMailer->getToAddresses())),
                'from' => maybe_serialize($this->phpMailer->From),
                'subject' => $this->phpMailer->Subject . ' (Simulated)',
                'body' => $this->phpMailer->Body,
                'attachments' => maybe_serialize($this->phpMailer->getAttachments()),
                'status'   => 'sent',
                'response' => maybe_serialize(['status' => 'Email sending was simulated, No Email was sent originally']),
                'headers'  => maybe_serialize($this->phpMailer->getCustomHeaders()),
                'extra'    => maybe_serialize(['provider' => 'Simulator'])
            ];
            (new Logger)->add($logData);
        }

        return true;
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

            if (!fluentMailIsListedSenderEmail($fromEmail)) {
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
