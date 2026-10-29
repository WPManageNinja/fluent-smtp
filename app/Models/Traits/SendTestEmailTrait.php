<?php

namespace FluentMail\App\Models\Traits;

trait SendTestEmailTrait
{
    public function sendTestEmail($data, $settings)
    {
        if (empty($settings) || empty($data)) return;

        $to = $data['email'];

        $subject = sprintf(__('Fluent SMTP: Test Email - %s', 'fluent-smtp'), fluentMailSiteTitle());

        if ($data['isHtml'] == 'true') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $body = (string)fluentMail('view')->make('admin.email_html');
            $subject .= ' - ' . __('HTML Version', 'fluent-smtp');
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            // Plain text body is not rendered as HTML, so decode the entities added by esc_html
            $body = wp_specialchars_decode((string)fluentMail('view')->make('admin.email_text'), ENT_QUOTES);
            $subject .= ' - ' . __('Text Version', 'fluent-smtp');
        }

        if (!empty($data['from'])) {
            $headers[] = 'From: ' . $data['from'];
        }

        if (!defined('FLUENTMAIL_TEST_EMAIL')) {
            define('FLUENTMAIL_TEST_EMAIL', true);
        }

        return wp_mail($to, $subject, $body, $headers);
    }
}
