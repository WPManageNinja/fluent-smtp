<?php

return function () {
    /*
     * The log list and the viewer's Prev and Next read the same search box.
     * Each case runs one search through the list's /logs route and walks
     * Logger::navigate() forward from the start and back from the end; all
     * three must give the same rows.
     */
    $seed = function ($table) {
        $ids = [];
        $ids['crm'] = FsmtpFactory::insertLog($table, [
            'subject' => 'Newsletter', 'status' => 'sent', 'body' => 'hello',
            'extra'   => serialize(['custom_headers' => [['key' => 'List-Unsubscribe', 'value' => '<https://example.test/u?h=1>']]]),
        ]);
        $ids['invoice'] = FsmtpFactory::insertLog($table, [
            'subject' => 'Your order', 'status' => 'failed', 'body' => 'invoice 42', 'extra' => serialize([]),
            'response' => serialize(['message' => 'boom']),
        ]);
        $ids['plain'] = FsmtpFactory::insertLog($table, [
            'subject' => 'Alpha notice', 'status' => 'failed', 'body' => 'nothing', 'extra' => serialize([]),
        ]);
        $ids['sent'] = FsmtpFactory::insertLog($table, [
            'subject' => 'Alpha sent', 'status' => 'sent', 'body' => 'nothing', 'extra' => serialize([]),
        ]);
        return $ids;
    };

    $both = function ($table, $search, $status, $dateRange) {
        global $wpdb;
        $redirect = FsmtpFactory::productionLogTableRedirect($table);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            // The list through its real route, which pages from the request.
            $listParams = ['search' => $search, 'status' => $status, 'page' => 1, 'per_page' => 50];
            if ($dateRange) {
                $listParams['date_range'] = $dateRange;
            }
            $list = FsmtpTest::ajax('GET', '/logs', $listParams);
            $listIds = array_map('intval', array_column(isset($list['data']['data']) ? $list['data']['data'] : [], 'id'));
            sort($listIds);

            $logger = new \FluentMail\App\Models\Logger();

            $filter = $status ? ['filter_by' => 'status', 'filter_by_value' => $status] : [];
            if ($dateRange) {
                $filter['date_range'] = $dateRange;
            }
            $walk = function ($dir, $id) use ($logger, $filter, $search, $wpdb) {
                $ids = [];
                for ($i = 0; $i < 10; $i++) {
                    $step = $logger->navigate(array_merge($filter, ['query' => $search, 'id' => $id, 'dir' => $dir]));
                    FsmtpTest::assertSame('', (string) $wpdb->last_error, 'navigation database error');
                    if (empty($step['log']['id'])) {
                        break;
                    }
                    $id = (int) $step['log']['id'];
                    $ids[] = $id;
                }
                return $ids;
            };
            $nav = $walk('next', 0);
            $back = array_reverse($walk('prev', PHP_INT_MAX));
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
        }

        return ['list' => $listIds, 'nav' => $nav, 'back' => $back];
    };

    $today = gmdate('Y-m-d');
    $cases = [
        'a column search on extra'                 => ['extra:List-Unsubscribe', '', ['crm'], null],
        'a column search on body'                  => ['body:invoice', '', ['invoice'], null],
        'a column search on a searchable column'   => ['subject:Alpha', '', ['plain', 'sent'], null],
        'a column prefix with no text'             => ['response:', '', ['invoice'], null],
        'a plain search'                           => ['Alpha', '', ['plain', 'sent'], null],
        'a search of 0, which the list ignores'    => ['0', '', ['crm', 'invoice', 'plain', 'sent'], null],
        'status:failed with the Failed filter on'  => ['status:failed', 'failed', ['invoice', 'plain'], null],
        'status:sent with the Failed filter on'    => ['status:sent', 'failed', [], null],
        'a plain search with the Failed filter on' => ['Alpha', 'failed', ['plain'], null],
        'a plain search with a date range'         => ['Alpha', '', ['plain', 'sent'], [$today, $today]],
    ];

    foreach ($cases as $title => $case) {
        FsmtpTest::case('the viewer walks the same rows as the list for ' . $title, function () use ($seed, $both, $case) {
            list($search, $status, $expectedKeys, $dateRange) = $case;
            $table = FsmtpFactory::emailLogTable();
            try {
                $ids = $seed($table);
                $expected = array_map(function ($key) use ($ids) {
                    return (int) $ids[$key];
                }, $expectedKeys);
                sort($expected);

                $result = $both($table, $search, $status, $dateRange);

                FsmtpTest::assertSame($expected, $result['list'], 'list rows');
                FsmtpTest::assertSame($expected, $result['nav'], 'viewer rows walking Next');
                FsmtpTest::assertSame($expected, $result['back'], 'viewer rows walking Prev');
            } finally {
                FsmtpFactory::dropTable($table);
            }
        });
    }
};
