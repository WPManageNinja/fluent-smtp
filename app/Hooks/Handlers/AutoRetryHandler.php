<?php

namespace FluentMail\App\Hooks\Handlers;

use FluentMail\App\Models\Logger;
use FluentMail\Includes\Support\Arr;

/**
 * Automatically retries failed email sends with exponential backoff.
 *
 * A failed send (primary + fallback) is retried via WP-Cron single events,
 * reusing the same code path as the manual "Retry" button. Failure
 * notifications are held back until the final attempt has failed.
 */
class AutoRetryHandler
{
    const HOOK = 'fluentsmtp_auto_retry_email';

    /* Never schedule when the log row already accumulated this many retries
       (fallback attempts also increment the counter). */
    const MAX_TOTAL_RETRIES = 5;

    /* Rows older than this are notified instead of retried (seconds). */
    const STALE_AFTER = 86400;

    /* Sweep picks up cycles whose next attempt is overdue by this (seconds). */
    const SWEEP_OVERDUE = 3600;

    public function register()
    {
        add_action('fluentmail_email_sending_failed_no_fallback', array($this, 'maybeScheduleRetry'), 5, 3);
        add_action(self::HOOK, array($this, 'executeRetry'), 10, 2);
    }

    public static function isEnabled()
    {
        $settings = fluentMailGetSettings();
        return Arr::get($settings, 'misc.auto_retry_failed_emails') == 'yes';
    }

    public function maybeScheduleRetry($logId, $handler = null, $data = [])
    {
        if (!$logId || !self::isEnabled() || defined('FLUENTMAIL_EMAIL_TESTING')) {
            return;
        }

        $row = $this->getLogRow($logId);

        if (!$row || $row['status'] !== Logger::STATUS_FAILED) {
            return;
        }

        if (Arr::get($row, 'extra.auto_retry')) {
            // Already part of a retry cycle (including an exhausted one,
            // when this action is re-fired for the final notification).
            return;
        }

        if (intval($row['retries']) >= self::MAX_TOTAL_RETRIES) {
            return;
        }

        $this->scheduleAttempt($logId, 1, $row);
    }

    /**
     * Whether failure notifications should stay silent for this log row
     * because an auto-retry attempt is still pending.
     *
     * Called from SchedulerHandler::maybeSendNotification at hook priority 10;
     * maybeScheduleRetry (priority 5) has already stamped the row by then.
     */
    public static function willRetry($logId)
    {
        // @phpstan-ignore-next-line (new static() for late-static-binding test seams)
        return (new static())->checkWillRetry($logId);
    }

    protected function checkWillRetry($logId)
    {
        if (!$logId || !self::isEnabled()) {
            return false;
        }

        $row = $this->getLogRow($logId);

        if (!$row || $row['status'] !== Logger::STATUS_FAILED) {
            return false;
        }

        $stamp = Arr::get($row, 'extra.auto_retry');

        if (!$stamp || !empty($stamp['exhausted'])) {
            return false;
        }

        if (intval($row['retries']) >= self::MAX_TOTAL_RETRIES) {
            return false;
        }

        return intval(Arr::get($stamp, 'attempt', 0)) <= count($this->getRetryDelays());
    }

    protected function scheduleAttempt($logId, $attempt, array $row)
    {
        $delays = $this->getRetryDelays();
        $delay = intval($delays[$attempt - 1]);
        $timestamp = time() + $delay;

        // Stamp before scheduling so a lost/failed schedule is sweep-visible.
        $this->stampLog($row, array('attempt' => $attempt, 'next_at' => $timestamp));

        wp_schedule_single_event($timestamp, self::HOOK, array(intval($logId), $attempt));
    }

    protected function getRetryDelays()
    {
        $delays = apply_filters('fluentmail_auto_retry_delays', array(120, 900, 3600));

        if (!is_array($delays) || !$delays) {
            $delays = array(120, 900, 3600);
        }

        return array_values($delays);
    }

    protected function getLogRow($logId)
    {
        $row = fluentMailDb()->table(FLUENT_MAIL_DB_PREFIX . 'email_logs')
            ->where('id', intval($logId))
            ->first();

        if (!$row) {
            return null;
        }

        $row = (array)$row;

        if (!empty($row['extra']) && is_string($row['extra']) && is_serialized($row['extra'])) {
            $row['extra'] = unserialize(trim($row['extra']), array('allowed_classes' => false));
        }

        return $row;
    }

    protected function stampLog(array $row, array $stamp)
    {
        $extra = isset($row['extra']) && is_array($row['extra']) ? $row['extra'] : array();
        $existing = isset($extra['auto_retry']) && is_array($extra['auto_retry']) ? $extra['auto_retry'] : array();
        $extra['auto_retry'] = array_merge($existing, $stamp);

        (new Logger())->updateLog(array(
            'extra'      => serialize($extra),
            'updated_at' => current_time('mysql'),
        ), array('id' => $row['id']));
    }

    /**
     * Cron callback: run one retry attempt for a failed email log row.
     */
    public function executeRetry($logId, $attempt = 1)
    {
        $row = $this->getLogRow($logId);

        if (!$row || $row['status'] !== Logger::STATUS_FAILED) {
            return; // Sent meanwhile or handled manually.
        }

        if (!self::isEnabled()) {
            return; // Feature switched off mid-cycle: leave the row as-is.
        }

        $this->runResendAttempt($logId);

        $row = $this->getLogRow($logId);

        if (!$row || $row['status'] !== Logger::STATUS_FAILED) {
            return; // Retry succeeded.
        }

        $delays = $this->getRetryDelays();

        if ($attempt < count($delays) && intval($row['retries']) < self::MAX_TOTAL_RETRIES) {
            $this->scheduleAttempt($logId, $attempt + 1, $row);
            return;
        }

        $this->finishRetryCycle($row);
    }

    protected function runResendAttempt($logId)
    {
        try {
            (new Logger())->resendEmailFromLog($logId, 'check_realtime');
        } catch (\Exception $e) {
            // A throwing attempt counts as a failed attempt; the row keeps
            // its 'failed' status and the cycle continues.
            error_log('FluentSMTP auto-retry attempt failed for log #' . intval($logId) . ': ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    /**
     * Mark the retry cycle exhausted and release the held-back notification.
     */
    protected function finishRetryCycle(array $row)
    {
        $this->stampLog($row, array('exhausted' => true));

        $handler = $this->resolveHandler($row);

        if (!$handler) {
            error_log('FluentSMTP auto-retry: no provider resolvable for final failure notification of log #' . $row['id']); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return;
        }

        do_action('fluentmail_email_sending_failed_no_fallback', $row['id'], $handler, $row);
    }

    /**
     * The original in-memory handler object is gone in the cron process;
     * resolve one from the log row's from address for notification formatting.
     */
    protected function resolveHandler(array $row)
    {
        $email = $this->extractEmailAddress(Arr::get($row, 'from', ''));

        $handler = $email ? fluentMailGetProvider($email) : false;

        if (!$handler) {
            $default = fluentMailDefaultConnection();
            if ($default && !empty($default['sender_email'])) {
                $handler = fluentMailGetProvider($default['sender_email']);
            }
        }

        return $handler;
    }

    protected function extractEmailAddress($from)
    {
        $from = html_entity_decode((string)$from, ENT_QUOTES);

        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return trim($matches[1]);
        }

        return trim($from);
    }

    /**
     * Safety net, run from the existing daily scheduled task: rescue retry
     * cycles whose WP-Cron single event was lost, so no email ends up
     * neither retried nor notified.
     */
    public function sweepStrandedRetries()
    {
        if (!self::isEnabled()) {
            return;
        }

        $rows = $this->querySweepCandidates();

        foreach ($rows as $rowObj) {
            $row = $this->getLogRow(is_object($rowObj) ? $rowObj->id : $rowObj['id']);

            if (!$row || $row['status'] !== Logger::STATUS_FAILED) {
                continue;
            }

            $stamp = Arr::get($row, 'extra.auto_retry');

            if (!$stamp || !empty($stamp['exhausted'])) {
                continue;
            }

            $nextAt = intval(Arr::get($stamp, 'next_at', 0));

            if (!$nextAt || (time() - $nextAt) < self::SWEEP_OVERDUE) {
                continue; // Not overdue (or malformed): leave to the scheduled event.
            }

            $attempt = intval(Arr::get($stamp, 'attempt', 1));

            if (wp_next_scheduled(self::HOOK, array(intval($row['id']), $attempt))) {
                continue; // The event still exists; WP-Cron is merely behind.
            }

            $createdAt = strtotime($row['created_at'] . ' UTC');

            if ($createdAt && (time() - $createdAt) > self::STALE_AFTER) {
                // Too old to be worth resending; release the notification.
                $this->finishRetryCycle($row);
                continue;
            }

            $this->executeRetry($row['id'], $attempt);
        }
    }

    protected function querySweepCandidates()
    {
        return fluentMailDb()->table(FLUENT_MAIL_DB_PREFIX . 'email_logs')
            ->where('status', Logger::STATUS_FAILED)
            ->where('extra', 'LIKE', '%auto_retry%')
            ->where('updated_at', '>', gmdate('Y-m-d H:i:s', time() - 7 * 86400))
            ->orderBy('id', 'ASC')
            ->limit(20)
            ->get();
    }
}
