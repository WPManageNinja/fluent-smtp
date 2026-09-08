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

// Preserve the docs caches: smoke fixtures must not become the site's saved feed.
$docsOptions = [];
foreach (['_transient_fluent_smtp_docs_v1', '_transient_timeout_fluent_smtp_docs_v1', 'fluent_smtp_docs_v1_last_success'] as $name) {
    $docsOptions[$name] = get_option($name);
}
register_shutdown_function(function () use ($docsOptions) {
    delete_transient('fluent_smtp_docs_v1');
    foreach ($docsOptions as $name => $value) {
        if ($value === false) {
            delete_option($name);
        } else {
            update_option($name, $value, false);
        }
    }
});
delete_transient('fluent_smtp_docs_v1');

// The docs route intentionally reads a remote public feed. Exercise the
// documentation feed with a deterministic fixture and block every other
// outbound request (notification/provider calls included).
FsmtpTest::interceptHttp(function ($url) {
    if ($url === 'https://fluentsmtp.com/docs/api/v1/docs.json') {
        $body = wp_json_encode(['version' => 1, 'docs' => [[
            'id' => 'test-document',
            'title' => 'Test document',
            'description' => 'Fixture description',
            'content' => '# Fixture',
            'link' => 'https://fluentsmtp.com/docs/test-document/',
            'category' => ['value' => 'testing', 'label' => 'Testing'],
        ]]]);

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

            $strictHeatmapFailure = $entry['route'] === '/day-time-stats'
                && strpos($result['db_error'], 'ORDER BY clause is not in GROUP BY clause') !== false
                && strpos($result['db_error'], 'only_full_group_by') !== false;
            if (FsmtpTest::knownFailure(
                $strictHeatmapFailure,
                'heatmap ordering is rejected by ONLY_FULL_GROUP_BY (app/Http/Controllers/DashboardController.php:60-62,79-81).'
            )) {
                return;
            }

            $strictReportFailure = $entry['route'] === 'sending_stats'
                && strpos($result['db_error'], 'SELECT list is not in GROUP BY clause') !== false
                && strpos($result['db_error'], 'only_full_group_by') !== false;
            if (FsmtpTest::knownFailure(
                $strictReportFailure,
                'grouped reporting SELECT is rejected by ONLY_FULL_GROUP_BY (app/Services/Reporting.php:40,45,58).'
            )) {
                return;
            }

            FsmtpTest::assertAjaxHealthy(
                $result,
                $entry['method'] . ' ' . $entry['route'] . ' [' . $variation['label'] . ']'
            );
        });
    }
}

FsmtpTest::finish('AJAX SMOKE');
