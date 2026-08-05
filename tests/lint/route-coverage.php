<?php
/**
 * Phase 2 — fail when app/Http/routes.php and the AJAX manifest drift apart.
 */

$root = dirname(__DIR__, 2);
$config = require $root . '/tests/suite.config.php';
$routesPath = $root . '/' . $config['routes_file'];
$manifestPath = $root . '/' . $config['ajax_manifest'];

$source = file_get_contents($routesPath);
if ($source === false) {
    fwrite(STDERR, "route-coverage: cannot read {$routesPath}\n");
    exit(2);
}

preg_match_all(
    '/\$app->(get|post)\(\s*([\'\"])(.*?)\2\s*,\s*([\'\"])(.*?)\4\s*\)/',
    $source,
    $matches,
    PREG_SET_ORDER
);

$canonical = function ($route) {
    return $route === '/' ? '/' : ltrim($route, '/');
};

$declared = [];
foreach ($matches as $match) {
    $method = strtoupper($match[1]);
    $route = $canonical($match[3]);
    $key = $method . ' ' . $route;
    if (isset($declared[$key])) {
        fwrite(STDERR, "route-coverage: duplicate declaration {$key}\n");
        exit(1);
    }
    $declared[$key] = $match[5];
}

$manifest = require $manifestPath;
if (!is_array($manifest)) {
    fwrite(STDERR, "route-coverage: manifest must return an array\n");
    exit(2);
}

$listed = [];
$errors = [];
foreach ($manifest as $index => $entry) {
    foreach (['method', 'route', 'handler', 'source', 'cases'] as $required) {
        if (!array_key_exists($required, $entry)) {
            $errors[] = "manifest entry {$index} is missing {$required}";
        }
    }
    if ($errors && !isset($entry['method'], $entry['route'])) {
        continue;
    }

    $method = strtoupper((string) $entry['method']);
    $route = $canonical((string) $entry['route']);
    $key = $method . ' ' . $route;
    if (isset($listed[$key])) {
        $errors[] = "duplicate manifest entry {$key}";
    }
    $listed[$key] = isset($entry['handler']) ? $entry['handler'] : '';

    if (!in_array($method, ['GET', 'POST'], true)) {
        $errors[] = "{$key} uses unsupported method {$method}";
    }
    if (empty($entry['cases']) || !is_array($entry['cases'])) {
        $errors[] = "{$key} has no request cases";
    } else {
        $labels = [];
        foreach ($entry['cases'] as $caseIndex => $case) {
            if (!isset($case['label'], $case['params']) || !is_array($case['params'])) {
                $errors[] = "{$key} case {$caseIndex} needs label and params";
                continue;
            }
            if (isset($labels[$case['label']])) {
                $errors[] = "{$key} repeats case label {$case['label']}";
            }
            $labels[$case['label']] = true;
        }
    }

    if (!empty($entry['source'])) {
        $sourceFile = preg_replace('/:\d+$/', '', $entry['source']);
        if (!is_file($root . '/' . $sourceFile)) {
            $errors[] = "{$key} cites missing source file {$sourceFile}";
        }
    }
}

foreach ($declared as $key => $handler) {
    if (!array_key_exists($key, $listed)) {
        $errors[] = "route declaration missing from manifest: {$key}";
        continue;
    }
    if ($listed[$key] !== $handler) {
        $errors[] = "handler drift for {$key}: routes file={$handler}, manifest={$listed[$key]}";
    }
}

foreach ($listed as $key => $handler) {
    if (!array_key_exists($key, $declared)) {
        $errors[] = "manifest route is not declared: {$key}";
    }
}

$methodCounts = ['GET' => 0, 'POST' => 0];
foreach (array_keys($declared) as $key) {
    $methodCounts[strtok($key, ' ')]++;
}

echo sprintf(
    "route-coverage: %d declared, %d manifested (GET %d, POST %d)\n",
    count($declared),
    count($listed),
    $methodCounts['GET'],
    $methodCounts['POST']
);

if ($errors) {
    echo "\nFAIL — route manifest drift:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

echo "OK — every FluentSMTP admin-AJAX route has one matching manifest entry.\n";
exit(0);
