<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\NotificationHelper;
use FluentMail\App\Services\Notification\Manager as NotificationManager;
use FluentMail\Includes\Request\Request;
use FluentMail\Includes\Support\Arr;

class DiscordController extends Controller
{
    public function registerSite(Request $request)
    {
        $this->verify();

        $formData = $request->get('settings', []);

        if (empty($formData['webhook_url'])) {
            return $this->sendError([
                'message' => __('A webhook URL is required.', 'fluent-smtp')
            ], 422);
        }

        // validate the webhook URL
        $webhookUrl = Arr::get($formData, 'webhook_url');
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return $this->sendError([
                'message' => __('Please provide a valid webhook URL.', 'fluent-smtp')
            ], 422);
        }

        if (empty($formData['channel_name'])) {
            return $this->sendError([
                'message' => __('A channel name is required.', 'fluent-smtp')
            ], 422);
        }

        NotificationHelper::updateChannelSettings('discord', [
            'status'       => 'yes',
            'channel_name' => sanitize_text_field(Arr::get($formData, 'channel_name')),
            'webhook_url'  => sanitize_url(Arr::get($formData, 'webhook_url')),
        ]);

        return $this->sendSuccess([
            'message' => __('Settings saved.', 'fluent-smtp'),
        ]);
    }

    public function sendTestMessage(Request $request)
    {
        $this->verify();

        // Let's update the notification status
        $settings = (new Settings())->notificationSettings();

        if (Arr::get($settings, 'discord.status') != 'yes') {
            return $this->sendError([
                'message' => __('Slack notifications are not enabled.', 'fluent-smtp')
            ], 422);
        }

        $message = __('Test message from ', 'fluent-smtp') . site_url() . '. ' . __('If you can read this, the connection is working.', 'fluent-smtp');

        $result = NotificationHelper::sendDiscordMessage($message, Arr::get($settings, 'discord.webhook_url'));

        if (is_wp_error($result)) {
            return $this->sendError([
                'message' => $result->get_error_message(),
                'errors'  => $result->get_error_data(),
            ], 422);
        }

        return $this->sendSuccess([
            'message'         => __('Test message sent.', 'fluent-smtp'),
            'server_response' => $result
        ]);
    }

    public function disconnect()
    {
        $this->verify();

        NotificationHelper::updateChannelSettings('discord', [
            'status'       => 'no',
            'webhook_url'  => '',
            'channel_name' => ''
        ]);

        return $this->sendSuccess([
            'message' => __('Discord has been disconnected.', 'fluent-smtp')
        ]);
    }
}
