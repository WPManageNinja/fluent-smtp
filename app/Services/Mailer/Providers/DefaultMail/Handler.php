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

        return $this->handleResponse(new \WP_Error(423, 'Something went wrong!', []) );
    }

    protected function postSend()
    {
        try {
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
