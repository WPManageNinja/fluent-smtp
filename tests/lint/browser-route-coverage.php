<?php
/** Keep the browser smoke manifest synchronized with every Vue admin route. */

$root = dirname(__DIR__, 2);
$routesPath = $root . '/resources/admin/routes.js';
$manifestPath = $root . '/tests/browser/admin-screen-smoke.mjs';

$routesSource = file_get_contents($routesPath);
$manifestSource = file_get_contents($manifestPath);
if ($routesSource === false || $manifestSource === false) {
    fwrite(STDERR, "browser-route-coverage: could not read routes or manifest\n");
    exit(2);
}

preg_match_all('/\bpath:\s*([\'\"])(.*?)\1/', $routesSource, $routeMatches);
preg_match_all('/\bhash:\s*([\'\"])(.*?)\1/', $manifestSource, $manifestMatches);

$declared = $routeMatches[2];
$listed = $manifestMatches[2];
$errors = [];

foreach (array_count_values($declared) as $route => $count) {
    if ($count > 1) {
        $errors[] = "duplicate Vue route {$route}";
    }
}
foreach (array_count_values($listed) as $route => $count) {
    if ($count > 1) {
        $errors[] = "duplicate browser manifest route {$route}";
    }
}

foreach (array_diff($declared, $listed) as $route) {
    $errors[] = "Vue route missing from browser smoke: {$route}";
}
foreach (array_diff($listed, $declared) as $route) {
    $errors[] = "browser smoke route is not declared: {$route}";
}

echo sprintf(
    "browser-route-coverage: %d declared, %d manifested\n",
    count($declared),
    count($listed)
);

if ($errors) {
    echo "\nFAIL — browser route manifest drift:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

echo "OK — every FluentSMTP Vue admin route has one browser mount check.\n";
exit(0);
