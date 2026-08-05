<?php
/**
 * Guards for mutation-audit survivors S1 and S4.
 *
 * Both cover aggregates whose output a user actually reads - the dashboard
 * heatmap and the daily digest email - through code paths the rest of the suite
 * reaches only incidentally.
 */

return function () {

    /** Run hard-coded production log queries against an isolated real table. */
    $redirectFixture = function ($table, callable $callback) {
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            return $callback();
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
        }
    };

    /*
     * S1. getDayTimeStats() runs a different query once last_day exceeds 6, and
     * that bounded branch is the one the dashboard actually requests. Every
     * other heatmap case asks for last_day = 0, which takes the all-time
     * branch, so nothing asserted what the bounded query returns - only that a
     * silly lookback gets clamped.
     */
    FsmtpTest::case('bounded day-time heatmap groups rows by weekday and hour', function () use ($redirectFixture) {
        $table = FsmtpFactory::emailLogTable(false, true);

        $recent = current_datetime()->modify('-2 days')->setTime(9, 0);
        $older = current_datetime()->modify('-3 days')->setTime(14, 0);
        $outside = current_datetime()->modify('-60 days')->setTime(9, 0);

        // Two rows in one weekday/hour group, one in another, one outside the
        // requested window - so a lost GROUP BY, a lost range predicate and a
        // miscounted bucket are all distinguishable.
        FsmtpFactory::insertLog($table, ['created_at' => $recent->format('Y-m-d H:i:s')]);
        FsmtpFactory::insertLog($table, ['created_at' => $recent->setTime(9, 45)->format('Y-m-d H:i:s')]);
        FsmtpFactory::insertLog($table, ['created_at' => $older->format('Y-m-d H:i:s')]);
        FsmtpFactory::insertLog($table, ['created_at' => $outside->format('Y-m-d H:i:s')]);

        try {
            $result = $redirectFixture($table, function () {
                return FsmtpTest::ajax('GET', '/day-time-stats', ['last_day' => 30]);
            });

            FsmtpTest::assertSame('', (string)$result['db_error'], 'bounded heatmap database error');
            FsmtpTest::assertAjaxHealthy($result, 'bounded day-time heatmap');

            $stats = isset($result['data']['stats']) ? $result['data']['stats'] : [];

            $recentCell = isset($stats[$recent->format('D')]['9:00'])
                ? $stats[$recent->format('D')]['9:00']
                : null;
            $olderCell = isset($stats[$older->format('D')]['14:00'])
                ? $stats[$older->format('D')]['14:00']
                : null;

            FsmtpTest::assertSame(2, $recentCell, 'bounded heatmap grouped bucket');
            FsmtpTest::assertSame(1, $olderCell, 'bounded heatmap second bucket');

            // The 60-day-old row shares a weekday/hour group with nothing else
            // asserted above, so its cell must stay empty.
            $outsideCell = isset($stats[$outside->format('D')]['9:00'])
                ? $stats[$outside->format('D')]['9:00']
                : null;
            if ($outside->format('D') !== $recent->format('D')) {
                FsmtpTest::assertSame(0, $outsideCell, 'row outside the lookback contributed a bucket');
            }

            $total = 0;
            foreach ($stats as $hours) {
                $total += array_sum($hours);
            }
            FsmtpTest::assertSame(3, $total, 'bounded heatmap total across every cell');
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });

    /*
     * S4. getSubjectCountStat() reports distinct subjects per status in the
     * daily digest email. The existing aggregate fixture asks for sent subjects
     * during January while its only failed subject sits in March, so the date
     * range hid whether the status predicate was doing anything at all.
     *
     * Here both statuses have a distinct subject inside the requested range,
     * so dropping the predicate changes both counts.
     */
    FsmtpTest::case('distinct-subject counts are scoped to the requested status', function () {
        $table = FsmtpFactory::emailLogTable(false, true);

        $rows = [
            ['status' => 'sent', 'subject' => 'alpha', 'created_at' => '2025-01-06 10:00:00'],
            ['status' => 'sent', 'subject' => 'alpha', 'created_at' => '2025-01-07 11:00:00'],
            ['status' => 'failed', 'subject' => 'delta', 'created_at' => '2025-01-08 12:00:00'],
            ['status' => 'sent', 'subject' => 'gamma', 'created_at' => '2025-02-01 13:00:00'],
        ];

        foreach ($rows as $row) {
            FsmtpFactory::insertLog($table, $row);
        }

        try {
            $logger = FsmtpFactory::loggerForTable($table);

            // alpha twice collapses to one distinct subject; delta is inside
            // the range but the wrong status; gamma is the right status but
            // outside the range.
            FsmtpTest::assertSame(
                1,
                $logger->getSubjectCountStat('sent', '2025-01-01', '2025-01-31'),
                'distinct sent subjects in range'
            );
            FsmtpTest::assertSame(
                1,
                $logger->getSubjectCountStat('failed', '2025-01-01', '2025-01-31'),
                'distinct failed subjects in range'
            );
        } finally {
            FsmtpFactory::dropTable($table);
        }
    });
};
