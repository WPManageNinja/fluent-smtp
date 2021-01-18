<?php

namespace FluentMail\App\Services\Mailer\Providers\Smtp;

require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';

use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\Smtp\ValidatorTrait;

use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    public function send()
    {
        if ($this->preSend()) {
            $this->mail = new PHPMailer(true);
            
            if ($this->getSetting('auto_tls') == 'no') {
                $this->mail->SMTPAutoTLS = false;
            }

            return $this->postSend();
        }

        $this->handleFailure(new Exception('Something went wrong!', 0));
    }

    protected function postSend()
    {
        try {
            $this->mail->isSMTP();
            $this->mail->Host = $this->getSetting('host');
            $this->mail->Port = $this->getSetting('port');

            if ($this->getSetting('auth') == 'yes') {
                $this->mail->SMTPAuth = true;
                $this->mail->Username = $this->getSetting('username');
                $this->mail->Password = $this->getSetting('password');
            }

            if (($encryption = $this->getSetting('encryption')) != 'none') {
                $this->mail->SMTPSecure = $encryption;
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

                $this->mail->setFrom($fromEmail, $fromName);
            } else {
                $this->mail->setFrom($fromEmail);
            }

            foreach ($this->getParam('to') as $to) {
                if (isset($to['name'])) {
                    $this->mail->addAddress($to['email'], $to['name']);
                } else {
                    $this->mail->addAddress($to['email']);
                }
            }

            foreach ($this->getParam('headers.reply-to') as $replyTo) {
                if (isset($replyTo['name'])) {
                    $this->mail->addReplyTo($replyTo['email'], $replyTo['name']);
                } else {
                    $this->mail->addReplyTo($replyTo['email']);
                }
            }

            foreach ($this->getParam('headers.cc') as $cc) {
                if (isset($cc['name'])) {
                    $this->mail->addCC($cc['email'], $cc['name']);
                } else {
                    $this->mail->addCC($cc['email']);
                }
            }

            foreach ($this->getParam('headers.bcc') as $bcc) {
                if (isset($bcc['name'])) {
                    $this->mail->addBCC($bcc['email'], $bcc['name']);
                } else {
                    $this->mail->addBCC($bcc['email']);
                }
            }

            if ($attachments = $this->getParam('attachments')) {
                foreach ($attachments as $attachment) {
                    $this->mail->addAttachment($attachment[0], $attachment[7]);
                }
            }

            if ($this->getParam('headers.content-type') == 'text/html') {
                $this->mail->isHTML(true);
            }

            $this->mail->Subject = $this->getSubject();
            $this->mail->Body = $this->getParam('message');

            $this->mail->send();

            return $this->handleSuccess();

        } catch(Exception $e) {
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
