<?php

namespace FluentMail\App\Services\Mailer\Providers\DefaultMail;

use Exception;
use FluentMail\App\Hooks\Handlers\BulkSendSessionHandler;
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
            /*
             * Do not select the transport unconditionally. wp_mail() already
             * puts the shared PHPMailer back into mail() mode at the top of
             * every send, before phpmailer_init fires — so by the time a
             * listener has run, whatever transport it chose is the one this
             * send is meant to use. Plenty of hosts point PHPMailer at their
             * own relay from that hook while FluentSMTP is set to PHP mail(),
             * and overriding it there breaks sending on those sites outright.
             *
             * That leaves one case worth undoing: a send that reaches this
             * handler without passing through that reset — a fallback after
             * the SMTP provider already pointed the persistent PHPMailer at a
             * relay, which would otherwise deliver through the connection that
             * just failed, with its host and credentials still loaded.
             */
            if (self::$smtpTransportClaimed) {
                $this->phpMailer->isMail();
                self::$smtpTransportClaimed = false;
            }

            // Honoring a declared transport means this send may leave over
            // SMTP, so it must not inherit a socket a bulk session left open
            // to this site's own relay.
            BulkSendSessionHandler::releaseForeignSend();

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
