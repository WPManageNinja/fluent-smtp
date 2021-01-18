<?php

namespace FluentMail\App\Models\Traits;

use FluentMail\App\Services\Mailer\EmailQueueProcessor;

trait SendTestEmailTrait
{
    public function sendTestEmail($data, $settings)
    {
        if (empty($settings) || empty($data)) return;
        
        $to = $data['email'];

        $subject = 'Fluent SMTP: Test Email';

        if ($data['isHtml'] == 'true') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $body = (string) fluentMail('view')->make('admin.email_html');
            $subject .= ' - HTML Version';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $body = (string) fluentMail('view')->make('admin.email_text');
            $subject .= ' - Text Version';
        }

        if (!empty($data['from'])) {
            $headers[] = 'From: ' . $data['from'];
        }

        if(!defined('FLUENTMAIL_TEST_EMAIL')) {
            define('FLUENTMAIL_TEST_EMAIL', true);
        }

        return wp_mail($to, $subject, $body, $headers);
    }
}
