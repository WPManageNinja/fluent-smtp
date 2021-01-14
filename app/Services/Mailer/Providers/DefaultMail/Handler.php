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

        $this->handleFailure(new Exception('Something went wrong!', 0));
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
        $response = [
            'message' => 'Oops!',
            'code' => $exception->getCode(),
            'errors' => [$exception->getMessage()]
        ];

        $this->processResponse(['response' => $response], false);

        $this->fireWPMailFailedAction($response);
    }
}
