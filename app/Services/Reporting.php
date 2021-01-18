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

        global $wpdb;

        $tableName = $wpdb->prefix.FLUENT_MAIL_DB_PREFIX.'email_logs';

        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT COUNT(id) AS count, DATE(created_at) AS date FROM `%1$s` WHERE `created_at` BETWEEN \'%2$s\' AND \'%3$s\' GROUP BY `%4$s` ORDER BY `%5$s` ASC',
            $tableName,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $groupBy,
            $orderBy
        ));

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
        $to = $to ? date('Y-m-d', strtotime( $to . " +1 days")) : '+1 days';
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
            wpFluent()->raw('COUNT(id) AS count'),
            wpFluent()->raw('DATE('.$dateField.') AS date')
        ];

        if ($frequency == static::$weekly) {
            $select[] = wpFluent()->raw('WEEK(created_at) week');
        } else if ($frequency == static::$monthly) {
            $select[] = wpFluent()->raw('MONTH(created_at) month');
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