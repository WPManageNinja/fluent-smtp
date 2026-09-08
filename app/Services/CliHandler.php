<?php

namespace FluentMail\App\Services;

use FluentMail\App\Models\Logger;
use FluentMail\App\Models\Settings;
use FluentMail\Includes\Support\Arr;

/**
 * WP-CLI commands for FluentSMTP.
 *
 * These matter most exactly when the admin UI is not an option: a broken
 * connection on a site whose login emails are the thing that stopped working,
 * a staging box behind HTTP auth, or a deploy script that wants to assert the
 * mail path still works before it hands over.
 */
class CliHandler
{
    /**
     * Send a test email through the active connection.
     *
     * ## OPTIONS
     *
     * [--to=<email>]
     * : Recipient. Defaults to the site admin email.
     *
     * [--from=<email>]
     * : Sender to route through. Defaults to the default connection.
     *
     * [--text]
     * : Send a plain text email instead of HTML.
     *
     * ## EXAMPLES
     *
     *     wp fluent-smtp test
     *     wp fluent-smtp test --to=me@example.com --text
     *
     * @when after_wp_load
     */
    public function test($args, $assocArgs)
    {
        $to = Arr::get($assocArgs, 'to', get_option('admin_email'));

        if (!is_email($to)) {
            \WP_CLI::error(sprintf(/* translators: %s: email address */ __('%s is not a valid email address.', 'fluent-smtp'), $to));
        }

        $data = [
            'email'  => $to,
            'from'   => Arr::get($assocArgs, 'from', ''),
            'isHtml' => isset($assocArgs['text']) ? 'false' : 'true'
        ];

        if (!defined('FLUENTMAIL_EMAIL_TESTING')) {
            define('FLUENTMAIL_EMAIL_TESTING', true);
        }

        $error = '';

        // wp_mail() reports a delivery failure through this action rather than
        // its return value, so the reason has to be caught as it goes past.
        add_action('wp_mail_failed', function ($wpError) use (&$error) {
            $error = $wpError->get_error_message();
        });

        $settings = new Settings();

        $startedAt = microtime(true);

        try {
            $result = $settings->sendTestEmail($data, $settings->get());
        } catch (\Throwable $e) {
            \WP_CLI::error($e->getMessage());
            return;
        }

        $seconds = round(microtime(true) - $startedAt, 3);

        if ($error) {
            \WP_CLI::error(sprintf(/* translators: 1: seconds elapsed, 2: error message */ __('Sending failed after %1$ss: %2$s', 'fluent-smtp'), $seconds, $error));
            return;
        }

        if (!$result) {
            \WP_CLI::error(sprintf(/* translators: %s: seconds elapsed */ __('Sending failed after %ss for an unreported reason.', 'fluent-smtp'), $seconds));
            return;
        }

        \WP_CLI::success(sprintf(/* translators: 1: recipient email address, 2: seconds elapsed */ __('Test email handed to the provider for %1$s in %2$ss.', 'fluent-smtp'), $to, $seconds));
    }

    /**
     * Check every configured connection and report the ones that are broken.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render output in a particular format. Accepts table, csv, json, yaml.
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *
     *     wp fluent-smtp health
     *     wp fluent-smtp health --format=json
     *
     * @when after_wp_load
     */
    public function health($args, $assocArgs)
    {
        $report = (new ConnectionHealth())->checkAll();

        if (!$report) {
            \WP_CLI::warning(__('No connections are configured.', 'fluent-smtp'));
            return;
        }

        $rows = [];

        foreach ($report as $item) {
            $rows[] = [
                'sender'   => Arr::get($item, 'sender_email'),
                'provider' => Arr::get($item, 'provider'),
                'status'   => Arr::get($item, 'status'),
                'message'  => Arr::get($item, 'message')
            ];
        }

        \WP_CLI\Utils\format_items(
            Arr::get($assocArgs, 'format', 'table'),
            $rows,
            ['sender', 'provider', 'status', 'message']
        );

        $failed = count(array_filter($rows, function ($row) {
            return $row['status'] === ConnectionHealth::STATUS_ERROR;
        }));

        if ($failed) {
            // A non-zero exit lets a deploy or monitoring script gate on this.
            \WP_CLI::error(sprintf(/* translators: 1: number of failing connections, 2: total number of connections */ __('%1$d of %2$d connection(s) need attention.', 'fluent-smtp'), $failed, count($rows)));
        }

        \WP_CLI::success(sprintf(/* translators: %d: number of connections */ __('All %d connection(s) are healthy.', 'fluent-smtp'), count($rows)));
    }

    /**
     * Show sending stats from the email log.
     *
     * ## EXAMPLES
     *
     *     wp fluent-smtp stats
     *
     * @when after_wp_load
     */
    public function stats($args, $assocArgs)
    {
        $stats = (new Logger())->getStats();

        \WP_CLI\Utils\format_items(
            Arr::get($assocArgs, 'format', 'table'),
            [
                ['metric' => 'sent', 'count' => Arr::get($stats, 'sent', 0)],
                ['metric' => 'failed', 'count' => Arr::get($stats, 'failed', 0)]
            ],
            ['metric', 'count']
        );
    }

    /**
     * Delete logged emails older than the retention window.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Delete logs older than this many days. Defaults to the configured
     * retention setting.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp fluent-smtp prune-logs
     *     wp fluent-smtp prune-logs --days=30 --yes
     *
     * @subcommand prune-logs
     * @when after_wp_load
     */
    public function prune_logs($args, $assocArgs)
    {
        $days = Arr::get($assocArgs, 'days');

        if ($days === null) {
            $days = Arr::get(fluentMailGetSettings(), 'misc.log_saved_interval_days');
        }

        $days = (int)$days;

        if ($days < 1) {
            \WP_CLI::error(__('Provide a positive --days value, or configure a log retention period first.', 'fluent-smtp'));
            return;
        }

        \WP_CLI::confirm(
            sprintf(/* translators: %d: number of days */ __('Permanently delete every logged email older than %d day(s)?', 'fluent-smtp'), $days),
            $assocArgs
        );

        $deleted = (new Logger())->deleteLogsOlderThan($days);

        \WP_CLI::success(sprintf(/* translators: %d: number of deleted log entries */ _n('Deleted %d log entry.', 'Deleted %d log entries.', (int)$deleted, 'fluent-smtp'), (int)$deleted));
    }
}
