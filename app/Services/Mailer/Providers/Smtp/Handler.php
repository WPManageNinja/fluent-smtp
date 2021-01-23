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

        $this->handleFailure(new \Exception('Something went wrong!', 0));
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

            return $this->handleSuccess();

        } catch(\PHPMailer\PHPMailer\Exception $e) {
            return $this->handleFailure($e);
        }
    }

    protected function handleSuccess()
    {   
        $data = [
            'response' => [
                'code' => 200,
                'message' => 'OK'
            ]
        ];

        return $this->processResponse($data, true);
    }

    protected function handleFailure($exception)
    {
        $response = [
            'code' => $exception->getCode(),
            'message' => 'Oops!',
            'errors' => [$exception->getMessage()]
        ];

        $this->processResponse($response, false);

        $this->fireWPMailFailedAction($response);
    }
}
