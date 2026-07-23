<?php

namespace FluentMail\App\Services\Mailer\Providers\DefaultMail;

use Exception;
use FluentMail\App\Services\Mailer\BaseHandler;

class Handler extends BaseHandler
{
    public function send()
    {
        if ($this->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong!', 'fluent-smtp'), []) );
    }

    protected function postSend()
    {
        try {
            // The persistent PHPMailer may still carry Mailer='smtp' (and that
            // relay's host/credentials) from a previous SMTP-connection send;
            // this transport must explicitly select PHP mail() every time.
            $this->phpMailer->isMail();
            $this->phpMailer->send();
            return $this->handleSuccess();
        } catch(Exception $e) {
            return $this->handleFailure($e);
        }
    }

    protected function handleSuccess()
    {   
        $data = [
            'code' => 200,
            'message' => 'OK'
        ];

        return $this->processResponse($data, true);
    }

    protected function handleFailure($exception)
    {
        $error = new \WP_Error($exception->getCode(), $exception->getMessage(), []);
        return $this->handleResponse($error);
    }
}
