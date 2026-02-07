<?php
namespace FluentMail\App\Services;

class Reporting
{
    protected static $daily = 'P1D';
    protected static $weekly = 'P1W';
    protected static $monthly = 'P1M';

    public function getSendingStats($from, $to)
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
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

        // Build dynamic SELECT clause based on groupBy parameter
        // to ensure the selected columns match the GROUP BY clause
        // Use MIN(DATE()) for ONLY_FULL_GROUP_BY compliance
        if ($groupBy === 'week') {
            // Use YEARWEEK to prevent merging weeks across different years
            // Mode 1 ensures weeks start on Monday (ISO 8601 standard)
            $selectClause = 'COUNT(id) AS count, MIN(DATE(created_at)) AS date, YEARWEEK(created_at, 1) AS week';
        } elseif ($groupBy === 'month') {
            // Use YYYY-MM format to prevent merging months across different years
            $selectClause = "COUNT(id) AS count, MIN(DATE(created_at)) AS date, DATE_FORMAT(created_at, '%Y-%m') AS month";
        } else {
            // Default: group by date (daily stats)
            $selectClause = 'COUNT(id) AS count, DATE(created_at) AS date';
        }

        // Only parameterize data values (dates), NOT table/column names
        // Column names are validated above against whitelist
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$selectClause}
                 FROM `{$tableName}`
                 WHERE `created_at` BETWEEN %s AND %s
                 GROUP BY `{$groupBy}`
                 ORDER BY `{$orderBy}` ASC",
                $from->format('Y-m-d'),
                $to->format('Y-m-d')
            )
        );

        return $this->getResult($period, $items);
    }

    protected function makeDatePeriod($from, $to, $interval = null)
    {
        $interval = $interval ?: static::$daily;

        return new \DatePeriod($from, new \DateInterval($interval), $to);
    }

    protected function makeFromDate($from)
    {
        $from = $from ?: '-7 days';

        return new \DateTime($from);
    }

    protected function makeToDate($to)
    {
        $to = $to ? gmdate('Y-m-d', strtotime( $to . " +1 days")) : '+1 days';
        return new \DateTime($to);
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
            $range[$date] = (int) $item->count;
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
            $range[$date] = 0;
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
