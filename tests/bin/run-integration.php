<?php
/** FluentSMTP integration runner for database-backed behavior cases. */

require_once dirname(__DIR__) . '/lib/harness.php';
require_once dirname(__DIR__) . '/lib/factory.php';

FsmtpTest::boot();
FsmtpTest::interceptHttp();
$adminId = get_current_user_id();

$files = glob(dirname(__DIR__) . '/integration/*.php');
$files = $files === false ? [] : $files;
sort($files);

$requested = [];
foreach ((array) $args as $argument) {
    if (strpos($argument, '--filter=') === 0) {
        $value = substr($argument, 9);
    } elseif (strpos($argument, 'filter=') === 0) {
        $value = substr($argument, 7);
    } else {
        continue;
    }
    $requested = array_values(array_filter(array_map('trim', explode(',', $value))));
}
if ($requested) {
    $files = array_values(array_filter($files, function ($file) use ($requested) {
        return in_array(basename($file), $requested, true);
    }));
}

WP_CLI::log(sprintf(
    "FluentSMTP integration — %d files%s\n",
    count($files),
    $requested ? ' (filtered)' : ''
));

$cleanup = function () {
    try {
        FsmtpFactory::cleanup();
        FsmtpFactory::cleanup();
    } catch (Throwable $e) {
        WP_CLI::warning('Fixture cleanup failed: ' . $e->getMessage());
    }
};
register_shutdown_function($cleanup);

if (!$files) {
    FsmtpTest::case('integration runner discovers test files', function () {
        FsmtpTest::fail('No tests/integration/*.php files were found.');
    });
}

try {
    foreach ($files as $file) {
        try {
            wp_set_current_user($adminId);
            $suite = require $file;
            if (!is_callable($suite)) {
                throw new RuntimeException(basename($file) . ' must return a callable.');
            }
            $suite();
        } catch (Throwable $e) {
            FsmtpTest::case('integration file loads: ' . basename($file), function () use ($e) {
                throw $e;
            });
        }
    }
} finally {
    wp_set_current_user($adminId);
    $cleanup();
}

FsmtpTest::finish('INTEGRATION');
