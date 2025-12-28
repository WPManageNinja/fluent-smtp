<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\NotificationHelper;
use FluentMail\Includes\Request\Request;
use FluentMail\Includes\Support\Arr;

class PushoverController extends Controller
{
    public function registerSite(Request $request)
    {
        $this->verify();

        $formData = $request->get('settings', []);

        if (empty($formData['api_token'])) {
            return $this->sendError([
                'message' => __('API Token is required', 'fluent-smtp')
            ], 422);
        }

        if (empty($formData['user_key'])) {
            return $this->sendError([
                'message' => __('User Key is required', 'fluent-smtp')
            ], 422);
        }

        NotificationHelper::updateChannelSettings('pushover', [
            'status'    => 'yes',
            'api_token' => sanitize_text_field(Arr::get($formData, 'api_token')),
            'user_key'  => sanitize_text_field(Arr::get($formData, 'user_key')),
        ]);

        return $this->sendSuccess([
            'message' => __('Your settings has been saved', 'fluent-smtp'),
        ]);
    }

    public function sendTestMessage(Request $request)
    {
        $settings = (new Settings())->notificationSettings();

        if (Arr::get($settings, 'pushover.status') != 'yes') {
            return $this->sendError([
                'message' => __('Pushover notification is not enabled', 'fluent-smtp')
            ], 422);
        }

        $result = NotificationHelper::sendTestPushoverMessage(
            Arr::get($settings, 'pushover.api_token'),
            Arr::get($settings, 'pushover.user_key')
        );

        if (is_wp_error($result)) {
            return $this->sendError([
                'message' => $result->get_error_message(),
                'errors'  => $result->get_error_data(),
            ], 422);
        }

        return $this->sendSuccess([
            'message'         => __('Test message sent successfully', 'fluent-smtp'),
            'server_response' => $result
        ]);
    }

    public function disconnect()
    {
        NotificationHelper::updateChannelSettings('pushover', [
            'status'    => 'no',
            'api_token' => '',
            'user_key'  => ''
        ]);

        return $this->sendSuccess([
            'message' => __('Pushover connection has been disconnected successfully', 'fluent-smtp')
        ]);
    }
}
