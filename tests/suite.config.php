<?php
/**
 * FluentSMTP local test-suite configuration.
 *
 * Keep plugin-specific wiring here. Runners and the harness consume these
 * values so they do not hardcode the install path, WordPress prefix, or AJAX
 * action format.
 */

return [
    'plugin_slug'       => 'fluent-smtp',
    'plugin_dir_hint'   => 'plugins/fluent-smtp',
    'routes_file'       => 'app/Http/routes.php',
    'table_prefix'      => 'fsmpt_',
    'protected_tables'  => ['fsmpt_email_logs'],
    'app_bootstrap'     => 'fluentMail',
    'request_class'     => 'FluentMail\\Includes\\Request\\Request',
    'sentinel_class'    => 'FluentMail\\Includes\\Core\\Application',
    'cache_groups'      => [],
    'loopback_filter'   => '',
    'ajax_manifest'     => 'tests/smoke/routes.manifest.php',
];
