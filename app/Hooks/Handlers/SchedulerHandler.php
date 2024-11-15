<?php

namespace FluentMail\App\Hooks\Handlers;

use FluentMail\App\Models\Logger;
use FluentMail\App\Models\Settings;
use FluentMail\App\Services\NotificationHelper;
use FluentMail\Includes\Support\Arr;

class SchedulerHandler
{
    protected $dailyActionName = 'fluentmail_do_daily_scheduled_tasks';

    public function register()
    {
        add_action($this->dailyActionName, array($this, 'handleScheduledJobs'));
        add_action('fluentmail_email_sending_failed', array($this, 'maybeHandleFallbackConnection'), 10, 3);

        add_action('fluentsmtp_renew_gmail_token', array($this, 'renewGmailToken'));

        add_action('fluentmail_email_sending_failed_no_fallback', array($this, 'maybeSendNotification'), 10, 3);

    }

    public function handleScheduledJobs()
    {
        $this->deleteOldEmails();
        $this->sendDailyDigest();
    }

    private function deleteOldEmails()
    {
        $settings = fluentMailGetSettings();
        $logSaveDays = intval(Arr::get($settings, 'misc.log_saved_interval_days'));
        if ($logSaveDays) {
            (new \FluentMail\App\Models\Logger())->deleteLogsOlderThan($logSaveDays);
        }
    }

    public function sendDailyDigest()
    {
        $settings = (new Settings())->notificationSettings();

        if ($settings['enabled'] != 'yes' || empty($settings['notify_days']) || empty($settings['notify_email'])) {
            return;
        }

        $currentDay = gmdate('D');
        if (!in_array($currentDay, $settings['notify_days'])) {
            return;
        }

        $sendTo = $settings['notify_email'];
        $sendTo = str_replace(['{site_admin}', '{admin_email}'], get_option('admin_email'), $sendTo);

        $sendToArray = explode(',', $sendTo);

        $sendToArray = array_filter($sendToArray, function ($email) {
            return is_email($email);
        });

        if (!$sendToArray) {
            return false;
        }

        // we can send a summary email
        $lastDigestSent = get_option('_fluentmail_last_email_digest');
        if ($lastDigestSent) {
            if ((time() - strtotime($lastDigestSent)) < 72000) {
                return false; // we don't want to send another email if sent time within 20 hours
            }
        } else {
            $lastDigestSent = gmdate('Y-m-d', strtotime('-7 days'));
        }

        // Let's create the stats
        $startDate = gmdate('Y-m-d 00:00:01', (strtotime($lastDigestSent) - 86400));
        $endDate = gmdate('Y-m-d 23:59:59', strtotime('-1 days'));

        $reportingDays = floor((strtotime($endDate) - strtotime($startDate)) / 86400);

        $loggerModel = new Logger();
        $sentCount = $loggerModel->getTotalCountStat('sent', $startDate, $endDate);

        $sentStats = [
            'total'           => $sentCount,
            'subjects'        => [],
            'unique_subjects' => 0
        ];
        if ($sentCount) {
            $sentStats['unique_subjects'] = $loggerModel->getSubjectCountStat('sent', $startDate, $endDate);
            $sentStats['subjects'] = $loggerModel->getSubjectStat('sent', $startDate, $endDate, 10);
        }

        $failedCount = $loggerModel->getTotalCountStat('failed', $startDate, $endDate);
        $failedStats = [
            'total'           => $sentCount,
            'subjects'        => [],
            'unique_subjects' => 0
        ];
        if ($failedCount) {
            $failedStats['unique_subjects'] = $loggerModel->getSubjectCountStat('failed', $startDate, $endDate);
            $failedStats['subjects'] = $loggerModel->getSubjectStat('failed', $startDate, $endDate);
        }

        $sentSubTitle = sprintf(
            __('Showing %1$s of %2$s different subject lines sent in the past %3$s', 'fluent-smtp'),
            number_format_i18n(count($sentStats['subjects'])),
            number_format_i18n($sentStats['unique_subjects']),
            ($reportingDays < 2) ? 'day' : $reportingDays . ' days'
        );

        $failedSubTitle = sprintf(
            __('Showing %1$s of %2$s different subject lines failed in the past %3$s', 'fluent-smtp'),
            number_format_i18n(count($failedStats['subjects'])),
            number_format_i18n($failedStats['unique_subjects']),
            ($reportingDays < 2) ? 'day' : $reportingDays . ' days'
        );

        $sentTitle = __('Emails Sent', 'fluent-smtp');
        if ($sentCount) {
            $sentTitle .= ' <span style="font-size: 12px; vertical-align: middle;">(' . number_format_i18n($sentCount) . ')</span>';
        }
        $failedTitle = __('Email Failures', 'fluent-smtp');
        if ($failedCount) {
            $failedTitle .= ' <span style="font-size: 12px; vertical-align: middle;">(' . number_format_i18n($failedCount) . ')</span>';
        }

        $reportingDate = gmdate(get_option('date_format'), strtotime($startDate));

        $data = [
            'sent'        => [
                'total'         => $sentCount,
                'title'         => $sentTitle,
                'subtitle'      => $sentSubTitle,
                'subject_items' => $sentStats['subjects']
            ],
            'fail'        => [
                'total'         => $failedCount,
                'title'         => $failedTitle,
                'subtitle'      => $failedSubTitle,
                'subject_items' => $failedStats['subjects']
            ],
            'date_range'  => $reportingDate,
            'domain_name' => $this->getDomainName()
        ];

        $emailBody = (string)fluentMail('view')->make('admin.digest_email', $data);
        $emailSubject = $reportingDate . ' email sending stats for ' . $this->getDomainName();

        $headers = array('Content-Type: text/html; charset=UTF-8');

        update_option('_fluentmail_last_email_digest', gmdate('Y-m-d H:i:s'));

        return wp_mail($sendToArray, $emailSubject, $emailBody, $headers);

    }

    private function getDomainName()
    {
        $parts = parse_url(site_url());
        $url = $parts['host'] . (isset($parts['path']) ? $parts['path'] : '');
        return untrailingslashit($url);
    }

    public function maybeHandleFallbackConnection($logId, $handler, $data = [])
    {
        if (defined('FLUENTMAIL_EMAIL_TESTING')) {
            return false;
        }

        $settings = (new \FluentMail\App\Models\Settings())->getSettings();

        $fallbackConnectionId = \FluentMail\Includes\Support\Arr::get($settings, 'misc.fallback_connection');

        if (!$fallbackConnectionId) {
            do_action('fluentmail_email_sending_failed_no_fallback', $logId, $handler, $data);
            return false;
        }

        $fallbackConnection = \FluentMail\Includes\Support\Arr::get($settings, 'connections.' . $fallbackConnectionId);

        if (!$fallbackConnection) {
            do_action('fluentmail_email_sending_failed_no_fallback', $logId, $handler, $data);
            return false;
        }

        $phpMailer = $handler->getPhpMailer();

        $fallbackSettings = $fallbackConnection['provider_settings'];
        $phpMailer->setFrom($fallbackSettings['sender_email'], $phpMailer->FromName);

        // Trap the fluentSMTPMail mailer here
        $phpMailer = new \FluentMail\App\Services\Mailer\FluentPHPMailer($phpMailer);
        return $phpMailer->sendViaFallback($logId);
    }

    public function renewGmailToken()
    {
        $settings = fluentMailGetSettings();

        if (!$settings) {
            return;
        }

        $connections = Arr::get($settings, 'connections', []);

        foreach ($connections as $connection) {
            if (Arr::get($connection, 'provider_settings.provider') != 'gmail') {
                continue;
            }
            $providerSettings = $connection['provider_settings'];
            if (($providerSettings['expire_stamp'] - 480) < time() && !empty($providerSettings['refresh_token'])) {
                $this->callGmailApiForNewToken($connection['provider_settings']);
            }
        }
    }

    public function callGmailApiForNewToken($settings)
    {
        if (Arr::get($settings, 'key_store') == 'wp_config') {
            $settings['client_id'] = defined('FLUENTMAIL_GMAIL_CLIENT_ID') ? FLUENTMAIL_GMAIL_CLIENT_ID : '';
            $settings['client_secret'] = defined('FLUENTMAIL_GMAIL_CLIENT_SECRET') ? FLUENTMAIL_GMAIL_CLIENT_SECRET : '';
        }

        if (!class_exists('\FluentSmtpLib\Google\Client')) {
            require_once FLUENTMAIL_PLUGIN_PATH . 'includes/libs/google-api-client/build/vendor/autoload.php';
        }

        try {
            $client = new \FluentSmtpLib\Google\Client();
            $client->setClientId($settings['client_id']);
            $client->setClientSecret($settings['client_secret']);
            $client->addScope("https://www.googleapis.com/auth/gmail.compose");
            $client->setAccessType('offline');
            $client->setApprovalPrompt('force');

            $tokens = [
                'access_token'  => $settings['access_token'],
                'refresh_token' => $settings['refresh_token'],
                'expires_in'    => $settings['expire_stamp'] - time()
            ];

            $client->setAccessToken($tokens);

            $newTokens = $client->refreshToken($tokens['refresh_token']);
            $result = $this->saveNewGmailTokens($settings, $newTokens);

            if (!$result) {
                return new \WP_Error('api_error', __('Failed to renew the token', 'fluent-smtp'));
            }

            return true;
        } catch (\Exception $exception) {
            return new \WP_Error('api_error', $exception->getMessage());
        }
    }


    public function maybeSendNotification($rowId, $handler, $logData = [])
    {
        $channel = NotificationHelper::getActiveChannelSettings();

        if (!$channel) {
            return false;
        }

        $lastNotificationSent = get_option('_fsmtp_last_notification_sent');
        if ($lastNotificationSent && (time() - $lastNotificationSent) < 60) {
            return false;
        }

        update_option('_fsmtp_last_notification_sent', time());

        $driver = $channel['driver'];
        if ($driver == 'telegram') {
            $data = [
                'token_id'      => $channel['token'],
                'provider'      => $handler->getSetting('provider'),
                'error_message' => $this->getErrorMessageFromResponse(maybe_unserialize(Arr::get($logData, 'response')))
            ];

            return NotificationHelper::sendFailedNotificationTele($data);
        }

        if ($driver == 'slack') {
            return NotificationHelper::sendSlackMessage(NotificationHelper::formatSlackMessageBlock($handler, $logData), $channel['webhook_url'], false);
        }

        if ($driver == 'discord') {
            return NotificationHelper::sendDiscordMessage(NotificationHelper::formatDiscordMessageBlock($handler, $logData), $channel['webhook_url'], false);
        }

        return false;
    }

    private function saveNewGmailTokens($existingData, $tokens)
    {
        if (empty($tokens['access_token']) || empty($tokens['refresh_token'])) {
            return false;
        }

        $senderEmail = $existingData['sender_email'];

        $existingData['access_token'] = $tokens['access_token'];
        $existingData['refresh_token'] = $tokens['refresh_token'];
        $existingData['expire_stamp'] = $tokens['expires_in'] + time();
        $existingData['expires_in'] = $tokens['expires_in'];

        (new Settings())->updateConnection($senderEmail, $existingData);
        fluentMailGetProvider($senderEmail, true); // we are clearing the static cache here
        wp_schedule_single_event($existingData['expire_stamp'] - 360, 'fluentsmtp_renew_gmail_token');
        return true;
    }

    private function getErrorMessageFromResponse($response)
    {
        if (!$response || !is_array($response)) {
            return '';
        }

        if (!empty($response['fallback_response']['message'])) {
            $message = $response['fallback_response']['message'];
        } else {
            $message = Arr::get($response, 'message');
        }

        if (!$message) {
            return '';
        }

        if (!is_string($message)) {
            $message = json_encode($message);
        }

        return $message;
    }
}
