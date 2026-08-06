<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\NotificationHelper;
use FluentMail\Includes\Request\Request;
use FluentMail\Includes\Support\Arr;

class GotifyController extends Controller
{
    public function registerSite(Request $request)
    {
        $this->verify();

        $formData = $request->get('settings', []);

        if (empty($formData['server_url'])) {
            return $this->sendError([
                'message' => __('Server URL is required', 'fluent-smtp')
            ], 422);
        }

        if (empty($formData['app_token'])) {
            return $this->sendError([
                'message' => __('Application Token is required', 'fluent-smtp')
            ], 422);
        }

        $serverUrl = NotificationHelper::sanitizeGotifyServerUrl(Arr::get($formData, 'server_url'));

        if (!$serverUrl) {
            return $this->sendError([
                'message' => __('Please provide a valid Gotify server URL starting with http:// or https://', 'fluent-smtp')
            ], 422);
        }

        NotificationHelper::updateChannelSettings('gotify', [
            'status'     => 'yes',
            'server_url' => $serverUrl,
            'app_token'  => sanitize_text_field(Arr::get($formData, 'app_token')),
        ]);

        return $this->sendSuccess([
            'message' => __('Your settings has been saved', 'fluent-smtp'),
        ]);
    }

    public function sendTestMessage(Request $request)
    {
        $this->verify();

        $settings = (new Settings())->notificationSettings();

        if (Arr::get($settings, 'gotify.status') != 'yes') {
            return $this->sendError([
                'message' => __('Gotify notification is not enabled', 'fluent-smtp')
            ], 422);
        }

        $result = NotificationHelper::sendTestGotifyMessage(
            Arr::get($settings, 'gotify.server_url'),
            Arr::get($settings, 'gotify.app_token')
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
        $this->verify();

        NotificationHelper::updateChannelSettings('gotify', [
            'status'     => 'no',
            'server_url' => '',
            'app_token'  => ''
        ]);

        return $this->sendSuccess([
            'message' => __('Gotify connection has been disconnected successfully', 'fluent-smtp')
        ]);
    }
}
