<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\NotificationHelper;
use FluentMail\Includes\Request\Request;
use FluentMail\Includes\Support\Arr;

class TelegramController extends Controller
{
    public function issuePinCode(Request $request)
    {
        $this->verify();

        $formData = $request->get('settings', []);

        $userEmail = sanitize_email(Arr::get($formData, 'user_email'));

        if (!is_email($userEmail)) {
            return $this->sendError([
                'message' => __('Please provide a valid email address', 'fluent-mail')
            ], 422);
        }

        $payload = [
            'admin_email' => $userEmail,
            'smtp_url'    => admin_url('options-general.php?page=fluent-mail#/'),
            'site_url'    => site_url(),
            'site_title'  => get_bloginfo('name'),
            'site_lang'   => get_bloginfo('language'),
        ];


        $activationData = NotificationHelper::issueTelegramPinCode($payload);

        if (is_wp_error($activationData)) {
            return $this->sendError([
                'message' => $activationData->get_error_message(),
                'errors'  => $activationData->get_error_data(),
            ], 422);
        }

        return $this->sendSuccess([
            'message'    => __('Awesome! Please activate the connection from your telegram account.', 'fluent-smtp'),
            'site_token' => Arr::get($activationData, 'site_token'),
            'site_pin'   => Arr::get($activationData, 'site_pin'),
        ]);
    }

    public function confirmConnection(Request $request)
    {
        $this->verify();

        $siteToken = $request->get('site_token', '');

        if (empty($siteToken)) {
            return $this->sendError([
                'message' => __('Please provide site token', 'fluent-smtp')
            ], 422);
        }


        $connectionInfo = NotificationHelper::getTelegramConnectionInfo($siteToken);

        if (is_wp_error($connectionInfo)) {
            return $this->sendError([
                'message' => $connectionInfo->get_error_message(),
                'errors'  => $connectionInfo->get_error_data(),
            ], 422);
        }

        // Let's update the notification status
        $previousSettings = (new Settings())->notificationSettings();

        $previousSettings['telegram_notify_status'] = 'yes';
        $previousSettings['telegram_notify_token'] = $siteToken;

        update_option('_fluent_smtp_notify_settings', $previousSettings, false);

        return $this->sendSuccess([
            'success' => true,
            'message' => __('Connection successful', 'fluent-smtp'),
        ]);
    }

    public function getTelegramConnectionInfo(Request $request)
    {
        $this->verify();

        $settings = (new Settings())->notificationSettings();

        if ($settings['telegram_notify_status'] != 'yes') {
            return $this->sendSuccess([
                'message'                => __('Telegram notification is not enabled', 'fluent-smtp'),
                'telegram_notify_status' => 'no'
            ], 200);
        }

        $siteToken = $settings['telegram_notify_token'];

        $connectionInfo = NotificationHelper::getTelegramConnectionInfo($siteToken);

        if (is_wp_error($connectionInfo)) {
            return $this->sendSuccess([
                'telegram_notify_status' => 'failed',
                'message'                => $connectionInfo->get_error_message(),
                'errors'                 => $connectionInfo->get_error_data(),
            ]);
        }

        return $this->sendSuccess([
            'telegram_notify_status' => 'yes',
            'telegram_receiver'      => Arr::get($connectionInfo, 'telegram_receiver', []),
        ]);
    }

}
