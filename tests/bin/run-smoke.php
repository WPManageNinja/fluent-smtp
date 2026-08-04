<?php
/**
 * Phase 1 — read-only admin-AJAX smoke.
 *
 * Every GET route is dispatched with every concrete variation represented by
 * the Vue 2 admin application. POST routes live in the same authoritative
 * manifest but are exercised by the permission and integration tiers.
 */

require_once dirname(__DIR__) . '/lib/harness.php';

FsmtpTest::boot();

$manifest = require dirname(__DIR__) . '/smoke/routes.manifest.php';
$filter = '';
foreach ((array) $args as $argument) {
    if (strpos($argument, '--filter=') === 0) {
        $filter = substr($argument, 9);
    } elseif (strpos($argument, 'filter=') === 0) {
        $filter = substr($argument, 7);
    }
}

// The docs route intentionally reads a remote public feed. Exercise the
// controller formatter with a deterministic fixture and block every other
// outbound request (notification/provider calls included).
FsmtpTest::interceptHttp(function ($url) {
    if (strpos($url, 'fluentsmtp.com/wp-json/wp/v2/docs') !== false) {
        $body = wp_json_encode([
            [
                'title' => ['rendered' => 'Test document'],
                'content' => ['rendered' => '<p>Fixture</p>'],
                'link' => 'https://example.test/docs/fixture',
                'taxonomy_info' => [
                    'doc_category' => [
                        ['value' => 'testing', 'label' => 'Testing'],
                    ],
                ],
            ],
        ]);

        return [
            'headers'  => [],
            'body'     => $body,
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies'  => [],
            'filename' => null,
        ];
    }

    return null;
});

/** Resolve manifest tokens without creating or changing site data. */
$resolveTokens = function () {
    global $wpdb;

    $logId = $wpdb->get_var(
        "SELECT id FROM {$wpdb->prefix}fsmpt_email_logs ORDER BY id DESC LIMIT 1"
    );

    $today = current_time('Y-m-d');
    return [
        'today'              => $today,
        '7_days_ago'         => gmdate('Y-m-d', strtotime($today . ' -7 days')),
        '30_days_ago'        => gmdate('Y-m-d', strtotime($today . ' -30 days')),
        '90_days_ago'        => gmdate('Y-m-d', strtotime($today . ' -90 days')),
        'log_id'             => $logId ? (int) $logId : null,
        'missing_connection' => 'fsmtp-suite-missing-connection',
        'search'             => 'fsmtp-suite-no-match',
    ];
};

$tokens = $resolveTokens();

/** Recursively replace exact manifest tokens without changing scalar types. */
$replaceTokens = function ($value) use (&$replaceTokens, $tokens) {
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = $replaceTokens($item);
        }
        return $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    foreach ($tokens as $token => $replacement) {
        if ($value === '{' . $token . '}') {
            return $replacement;
        }
        if ($replacement !== null) {
            $value = str_replace('{' . $token . '}', (string) $replacement, $value);
        }
    }
    return $value;
};

$getRoutes = array_values(array_filter($manifest, function ($entry) {
    return $entry['method'] === 'GET';
}));
$caseCount = array_sum(array_map(function ($entry) {
    return count($entry['cases']);
}, $getRoutes));

WP_CLI::log(sprintf(
    "FluentSMTP AJAX smoke — %d GET routes, %d variations%s\n",
    count($getRoutes),
    $caseCount,
    $filter ? ' (filter: ' . $filter . ')' : ''
));

foreach ($getRoutes as $entry) {
    foreach ($entry['cases'] as $variation) {
        $label = $variation['label'];
        if ($filter !== '' && stripos($entry['route'] . ' ' . $label, $filter) === false) {
            continue;
        }

        FsmtpTest::case($entry['method'] . ' ' . $entry['route'] . ' — ' . $label, function () use (
            $entry,
            $variation,
            $replaceTokens,
            $tokens
        ) {
            if (!empty($variation['needs_log']) && !$tokens['log_id']) {
                FsmtpTest::skip('no email log exists for the read-only viewer route');
                return;
            }

            $params = $replaceTokens($variation['params']);
            $result = FsmtpTest::ajax($entry['method'], $entry['route'], $params);
            FsmtpTest::assertAjaxHealthy(
                $result,
                $entry['method'] . ' ' . $entry['route'] . ' [' . $variation['label'] . ']'
            );
        });
    }
}

FsmtpTest::finish('AJAX SMOKE');
