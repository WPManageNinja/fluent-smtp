<?php
/**
 * Intentionally broken FluentSMTP lint fixture. Never loaded by WordPress.
 *
 * `php tests/lint/raw-sql-prefix.php tests/lint/fixtures` must report the three
 * bare `fsmpt_email_logs` identifiers below and ignore the safe controls.
 */

$brokenSelect = $db->selectRaw('fsmpt_email_logs.status, COUNT(*) AS total');
$brokenWhere = $db->whereRaw("fsmpt_email_logs.created_at < NOW()");
$brokenAggregate = $db->raw("SUM(CASE WHEN fsmpt_email_logs.status = 'sent' THEN 1 ELSE 0 END)");

global $wpdb;
$table = $wpdb->prefix . 'fsmpt_email_logs';
$safeInterpolated = $db->selectRaw("`{$table}`.`status`, COUNT(*) AS total");
$safeConcatenated = $db->raw('COUNT(' . $table . '.id) AS total');
$safeBareColumn = $db->raw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END)");
