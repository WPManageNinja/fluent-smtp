<?php

namespace FluentMail\App\Services\Mailer\Providers\Simulator;

use FluentMail\App\Models\Logger;
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
                'subject' => $this->phpMailer->Subject,
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
        $returnResponse = [
            'response' => 'OK'
        ];

        $this->response = $returnResponse;
        return $this->handleResponse($this->response);
    }

    public function setSettings($settings)
    {
        $this->settings = $settings;
        return $this;
    }
}
