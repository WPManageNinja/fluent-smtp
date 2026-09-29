<?php
namespace FluentMail\App\Services;

class Reporting
{
    protected static $daily = 'P1D';
    protected static $weekly = 'P1W';
    protected static $monthly = 'P1M';

    /**
     * Emails sent and emails failed, per bucket, across the range.
     *
     * The buckets are days, Mondays or months depending on how long the range is, and
     * every bucket in the range is present whether anything happened in it or not:
     *
     *     ['2026-09-04' => ['sent' => 12, 'failed' => 1], '2026-09-05' => [...]]
     *
     * A monthly report keys its buckets 'Sep 2026' instead. The two counts are what the
     * dashboard chart plots against each other, which is why they are counted here
     * rather than being one total the caller has to split.
     *
     * A log is 'sent', 'failed' or 'pending', and a resend writes 'sent' back over the
     * row it retried. Only a delivery that was actually reported as failing counts as
     * failed - a row still waiting on its send is neither, and counting it as a failure
     * would put a red spike on the chart for mail that has not been attempted yet.
     *
     * @return array<string, array{sent: int, failed: int}>
     */
    public function getSendingStats($from, $to)
    {
        $to = $this->makeToDate($to);
        $from = $this->makeFromDate($from, $to);

        $period = $this->makeDatePeriod(
            $from,
            $to,
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        // Validate column names against whitelist to prevent SQL injection
        $allowedColumns = ['date', 'week', 'month'];
        if (!in_array($groupBy, $allowedColumns, true) || !in_array($orderBy, $allowedColumns, true)) {
            // Fallback to safe default if validation fails
            $groupBy = 'date';
            $orderBy = 'date';
        }

        global $wpdb;

        // Table name is safe - constructed from constants and WordPress prefix
        $tableName = $wpdb->prefix . FLUENT_MAIL_DB_PREFIX . 'email_logs';

        $counts = $this->statusCounts();

        // Build dynamic SELECT clause based on groupBy parameter
        // to ensure the selected columns match the GROUP BY clause
        // Use deterministic bucket dates that align with DatePeriod for ONLY_FULL_GROUP_BY compliance
        if ($groupBy === 'week') {
            // Use YEARWEEK to prevent merging weeks across different years
            // Mode 1 ensures weeks start on Monday (ISO 8601 standard)
            // Use Monday of each week as deterministic bucket date for alignment with DatePeriod
            $selectClause = $counts . ', MIN(DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY))) AS date, YEARWEEK(created_at, 1) AS week';
        } elseif ($groupBy === 'month') {
            // Use YYYY-MM format to prevent merging months across different years
            // Use first day of month as deterministic bucket date for alignment with DatePeriod
            // Note: %% escapes % for wpdb->prepare() - will become single % in final SQL
            $selectClause = $counts . ", MIN(DATE_FORMAT(created_at, '%%Y-%%m-01')) AS date, DATE_FORMAT(created_at, '%%Y-%%m') AS month";
        } else {
            // Default: group by date (daily stats)
            $selectClause = $counts . ', DATE(created_at) AS date';
        }

        // Only parameterize data values (dates), NOT table/column names
        // Column names are validated above against whitelist
        /*
         * Half-open on the upper bound, the same comparison the log table filters with.
         * `created_at` is a datetime written in the site's timezone by
         * current_time('mysql'), so BETWEEN two dates dropped everything sent after
         * midnight on the last day of the range - the chart's own last bucket.
         */
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$selectClause}
                 FROM `{$tableName}`
                 WHERE `created_at` >= %s AND `created_at` < %s
                 GROUP BY `{$groupBy}`
                 ORDER BY `{$orderBy}` ASC",
                $from->format('Y-m-d 00:00:00'),
                $this->endBoundary($to)->format('Y-m-d 00:00:00')
            )
        );

        return $this->getResult($period, $items);
    }

    /*
     * The two counts, as one conditional aggregate over the same scan.
     *
     * Written as literals rather than as bound parameters because they are literals:
     * they come from Logger's own status constants and never from a request. 'resent'
     * is not written by this plugin - a resend puts 'sent' back on the row it retried -
     * but a log table that has been through other hands can carry it, and it means the
     * email went out.
     */
    protected function statusCounts()
    {
        return "SUM(CASE WHEN `status` IN ('sent', 'resent') THEN 1 ELSE 0 END) AS sent, "
            . "SUM(CASE WHEN `status` = 'failed' THEN 1 ELSE 0 END) AS failed";
    }

    /*
     * The period runs to the day after the last one asked for, because DatePeriod
     * leaves its end date out. Both ends of a range the reader picked are theirs, so
     * the last bucket has to be the day they chose and not the one before it.
     */
    protected function makeDatePeriod($from, $to, $interval = null)
    {
        $interval = $interval ?: static::$daily;

        return new \DatePeriod(
            $this->alignToBucket($from, $interval),
            new \DateInterval($interval),
            $this->endBoundary($to)
        );
    }

    protected function endBoundary($to)
    {
        return (clone $to)->modify('+1 day');
    }

    /*
     * A weekly or monthly report is grouped in SQL by the week or the month a row falls
     * in, dated from its Monday or from the first of the month, so the empty buckets
     * this period fills in have to start on the same day. Stepping seven days from
     * whichever weekday the range happened to begin on gave a chart two sets of labels
     * for the same weeks - the query's and the period's - and it drew both.
     */
    protected function alignToBucket($date, $interval)
    {
        if ($interval == static::$weekly) {
            return (clone $date)->modify('monday this week');
        }

        if ($interval == static::$monthly) {
            return (clone $date)->modify('first day of this month');
        }

        return clone $date;
    }

    /**
     * The first day of the range, as midnight in the site's timezone.
     *
     * The default is the seven days ending today, today included - not `-7 days` from
     * the moment the request arrived, which is what this used to be. Reading from a
     * time of day rather than from midnight made the period's last recurrence land a
     * few microseconds inside its end date, so the chart drew an eighth bucket dated
     * tomorrow with nothing in it.
     */
    protected function makeFromDate($from, $to = null)
    {
        if ($from) {
            return $this->siteDate($from);
        }

        $to = $to ?: $this->siteDate('today');

        return (clone $to)->modify('-6 days');
    }

    protected function makeToDate($to)
    {
        return $this->siteDate($to ?: 'today');
    }

    /*
     * Logs are stamped with current_time('mysql'), so the day a report is bucketed
     * under is a day in the site's timezone. gmdate() put a site running ahead of UTC
     * a day behind its own log table for the first hours of every day.
     */
    protected function siteDate($date)
    {
        $zone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(date_default_timezone_get());

        try {
            $date = new \DateTime($date, $zone);
        } catch (\Exception $e) {
            /*
             * Both ends of the range come off the request. A date DateTime cannot read
             * is a report of the default week, not an uncaught exception on the
             * dashboard.
             */
            $date = new \DateTime('today', $zone);
        }

        return $date->setTime(0, 0, 0);
    }

    protected function getFrequency($from, $to)
    {
        $numDays = $to->diff($from)->format("%a");

        if ($numDays > 62 && $numDays <= 181) {
            return static::$weekly;
        } else if ($numDays > 181) {
            return static::$monthly;
        }

        return static::$daily;
    }

    protected function getGroupAndOrder($frequency)
    {
        $orderBy = $groupBy = 'date';

        if ($frequency == static::$weekly) {
            $orderBy = $groupBy = 'week';
        } else if ($frequency == static::$monthly) {
            $orderBy = $groupBy = 'month';
        }

        return [$groupBy, $orderBy];
    }

    protected function prepareSelect($frequency, $dateField = 'created_at')
    {
        $select = [
            fluentMailDb()->raw('COUNT(id) AS count'),
            fluentMailDb()->raw('DATE('.$dateField.') AS date')
        ];

        if ($frequency == static::$weekly) {
            $select[] = fluentMailDb()->raw('WEEK(created_at) week');
        } else if ($frequency == static::$monthly) {
            $select[] = fluentMailDb()->raw('MONTH(created_at) month');
        }

        return $select;
    }

    protected function getResult($period, $items)
    {
        $range = $this->getDateRangeArray($period);

        $formatter = 'basicFormatter';

        if ($this->isMonthly($period)) {
            $formatter = 'monYearFormatter';
        }

        foreach ($items as $item) {
            $date = $this->{$formatter}($item->date);
            $range[$date] = [
                'sent'   => (int) $item->sent,
                'failed' => (int) $item->failed
            ];
        }

        return $range;
    }

    protected function getDateRangeArray($period)
    {
        $range = [];

        $formatter = 'basicFormatter';

        if ($this->isMonthly($period)) {
            $formatter = 'monYearFormatter';
        }

        foreach ($period as $date) {
            $date = $this->{$formatter}($date);
            // Both counts, so that a bucket nothing happened in has the same shape as
            // one that did and the chart never has to test for a missing key.
            $range[$date] = ['sent' => 0, 'failed' => 0];
        }

        return $range;
    }

    protected function basicFormatter($date)
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        return $date->format('Y-m-d');
    }

    protected function monYearFormatter($date)
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        return $date->format('M Y');
    }

    protected function isMonthly($period)
    {
        return !!$period->getDateInterval()->m;
    }
}
