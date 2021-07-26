<?php

namespace FluentMail\App\Hooks\Handlers;

use FluentMail\App\Models\Logger;
use FluentMail\App\Models\Settings;
use FluentMail\Includes\Support\Arr;

class SchedulerHandler
{
    protected $actionName = 'fluentmail_do_daily_scheduled_tasks';

    public function register()
    {
        add_action('init', array($this, 'initScheduler'));
        add_action($this->actionName, array($this, 'handleScheduledJobs'));
        add_action('fluentmail_email_sending_failed', array($this, 'maybeHandleFallbackConnection'), 10, 2);
    }

    public function initScheduler()
    {
        if (!wp_next_scheduled($this->actionName)) {
            wp_schedule_event(time(), 'daily', $this->actionName);
        }
    }

    public function handleScheduledJobs()
    {
        $this->deleteOldEmails();
        $this->sendDailyDigest();
    }

    private function deleteOldEmails()
    {
        $settings = get_option('fluentmail-settings', []);
        $logSaveDays = intval(Arr::get($settings, 'misc.log_saved_interval_days'));
        if ($logSaveDays) {
            (new \FluentMail\App\Models\Logger())->deleteLogsOlderThan($logSaveDays);
        }
    }

    public function sendDailyDigest()
    {
        $settings = (new Settings())->notificationSettings();

        if($settings['enabled'] != 'yes' || empty($settings['notify_days']) || empty($settings['notify_email'])) {
            return;
        }

        $currentDay = date('D');
        if(!in_array($currentDay, $settings['notify_days'])) {
            return;
        }

        $sendTo = $settings['notify_email'];
        $sendTo = str_replace('{admin_email}', get_option('admin_email'), $sendTo);

        $sendToArray = explode(',', $sendTo);

        $sendToArray = array_filter($sendToArray, function ($email) {
            return  is_email($email);
        });

        if(!$sendToArray) {
            return false;
        }

        // we can send a summary email
        $lastDigestSent = get_option('_fluentmail_last_email_digest');
        if($lastDigestSent) {
            if((time() - strtotime($lastDigestSent)) < 72000 ) {
                return false; // we don't want to send another email if sent time within 20 hours
            }
        } else {
            $lastDigestSent = date('Y-m-d', strtotime('-7 days'));
        }

        // Let's create the stats
        $startDate = date('Y-m-d 00:00:01', (strtotime($lastDigestSent) - 86400));
        $endDate = date('Y-m-d 23:59:59', strtotime('-1 days'));

        $reportingDays = floor((strtotime($endDate) - strtotime($startDate)) / 86400);

        $loggerModel = new Logger();
        $sentCount = $loggerModel->getTotalCountStat('sent', $startDate, $endDate);

        $sentStats = [
            'total' => $sentCount,
            'subjects' => [],
            'unique_subjects' => 0
        ];
        if($sentCount) {
            $sentStats['unique_subjects'] = $loggerModel->getSubjectCountStat('sent', $startDate, $endDate);
            $sentStats['subjects'] = $loggerModel->getSubjectStat('sent', $startDate, $endDate, 10);
        }

        $failedCount = $loggerModel->getTotalCountStat('failed', $startDate, $endDate);
        $failedStats = [
            'total' => $sentCount,
            'subjects' => [],
            'unique_subjects' => 0
        ];
        if($failedCount) {
            $failedStats['unique_subjects'] = $loggerModel->getSubjectCountStat('failed', $startDate, $endDate);
            $failedStats['subjects'] = $loggerModel->getSubjectStat('failed', $startDate, $endDate);
        }

        $sentSubTitle = sprintf(
            __('Showing %1$s of %2$s different subject lines sent in the past %3$s'),
            number_format_i18n(count($sentStats['subjects'])),
            number_format_i18n($sentStats['unique_subjects']),
            ($reportingDays < 2) ? 'day' : $reportingDays.' days'
        );

        $failedSubTitle = sprintf(
            __('Showing %1$s of %2$s different subject lines failed in the past %3$s'),
            number_format_i18n(count($failedStats['subjects'])),
            number_format_i18n($failedStats['unique_subjects']),
            ($reportingDays < 2) ? 'day' : $reportingDays.' days'
        );

        $sentTitle = __('Emails Sent', 'fluent-smtp');
        if($sentCount) {
            $sentTitle .= ' <span style="font-size: 12px; vertical-align: middle;">('.number_format_i18n($sentCount).')</span>';
        }
        $failedTitle = __('Email Failures', 'fluent-smtp');
        if($failedCount) {
            $failedTitle .= ' <span style="font-size: 12px; vertical-align: middle;">('.number_format_i18n($failedCount).')</span>';
        }

        $reportingDate = date(get_option('date_format'), strtotime($startDate));

        $data = [
            'sent' => [
                'total' => $sentCount,
                'title' => $sentTitle,
                'subtitle' => $sentSubTitle,
                'subject_items' => $sentStats['subjects']
            ],
            'fail' => [
                'total' => $failedCount,
                'title' => $failedTitle,
                'subtitle' => $failedSubTitle,
                'subject_items' => $failedStats['subjects']
            ],
            'date_range' => $reportingDate,
            'domain_name' => $this->getDomainName()
        ];

        $emailBody = (string) fluentMail('view')->make('admin.digest_email', $data);
        $emailSubject = $reportingDate. ' email sending stats for '.$this->getDomainName();

        $headers = array('Content-Type: text/html; charset=UTF-8');

        update_option('_fluentmail_last_email_digest', date('Y-m-d H:i:s'));

        return wp_mail($sendToArray, $emailSubject, $emailBody, $headers);

    }

    private function getDomainName()
    {
        $parts  = parse_url( site_url() );
        $url    = $parts['host'] . ( isset( $parts['path'] ) ? $parts['path'] : '' );
        return untrailingslashit( $url );
    }

    public function maybeHandleFallbackConnection($logId, $handler)
    {
        if(defined('FLUENTMAIL_EMAIL_TESTING')) {
            return false;
        }

        $settings = (new \FluentMail\App\Models\Settings())->getSettings();

        $fallbackConnectionId = \FluentMail\Includes\Support\Arr::get($settings, 'misc.fallback_connection');

        if (!$fallbackConnectionId) {
            return false;
        }

        $fallbackConnection = \FluentMail\Includes\Support\Arr::get($settings, 'connections.' . $fallbackConnectionId);

        if(!$fallbackConnection) {
            return false;
        }

        $phpMailer = $handler->getPhpMailer();

        $fallbackSettings = $fallbackConnection['provider_settings'];
        $phpMailer->setFrom($fallbackSettings['sender_email'], $phpMailer->FromName);

        // Trap the fluentSMTPMail mailer here
        $phpMailer = new \FluentMail\App\Services\Mailer\FluentPHPMailer($phpMailer);
        return $phpMailer->sendViaFallback($logId);
    }
}
