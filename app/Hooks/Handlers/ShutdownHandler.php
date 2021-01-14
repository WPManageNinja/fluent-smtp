<?php

namespace FluentMail\App\Hooks\Handlers;

use FluentMail\App\Models\Logger;
use FluentMail\App\Services\Mailer\EmailQueueProcessor;

class ShutdownHandler
{
    public function handle()
    {
        if (!defined('FLUENTMAIL_TESTING_EMAIL')) {
            if ($this->shouldDispatchRequest()) {
                return $this->dispatchRequest();
            }
        }
    }

    protected function shouldDispatchRequest()
    {
        $request = fluentMail('request');

        if ($request->exists('fluentmail_sending_emails')) {
            return false;
        }

        return (new Logger)->hasPendingEmails();
    }

    protected function dispatchRequest()
    {
        session_write_close();

        $url = admin_url('admin-ajax.php') . '?action=send_emails&fluentmail_sending_emails';

        return wp_remote_post($url, [
            'timeout' => '0.01',
            'blocking' => false,
            'httpversion' => '1.0',
            'cookies' => $_COOKIE,
            'sslverify' => apply_filters('https_local_ssl_verify', false)
        ]);
    }

    public function sendEmails()
    {
        EmailQueueProcessor::process();
    }
}
