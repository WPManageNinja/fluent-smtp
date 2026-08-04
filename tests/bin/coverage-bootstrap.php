<?php
/** Start PCOV as PHP's auto-prepend file, before WP-CLI and WordPress load. */

if ((string) getenv('FSMTP_COVERAGE_FILE') !== '') {
    if (!function_exists('pcov\\start') || ini_get('pcov.enabled') !== '1') {
        throw new RuntimeException('Coverage bootstrap requires pcov.enabled=1.');
    }

    \pcov\clear();
    \pcov\start();
    define('FSMTP_COVERAGE_STARTED_EARLY', true);
}
