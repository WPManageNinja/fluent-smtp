<?php

namespace FluentMailMigrations;

class EmailLogs
{
    /**
     * Migrate the table.
     *
     * @return void
     */
    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . FLUENT_MAIL_DB_PREFIX.'email_logs';

        // Table name is static/hard-coded, so wpdb->prepare() is sufficient
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
            $sql = "CREATE TABLE $table (
                `id` INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `site_id` INT UNSIGNED NULL,
                `to` VARCHAR(255),
                `from` VARCHAR(255),
                `subject` VARCHAR(255),
                `body` LONGTEXT NULL,
                `headers` LONGTEXT NULL,
                `attachments` LONGTEXT NULL,
                `status` VARCHAR(20) DEFAULT 'pending',
                `response` TEXT NULL,
                `extra` TEXT NULL,
                `retries` INT UNSIGNED NULL DEFAULT 0,
                `resent_count` INT UNSIGNED NULL DEFAULT 0,
                `source` VARCHAR(255) NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `created_at_status` (`created_at`, `status`)
            ) $charsetCollate;";

            dbDelta($sql);
        } else {
            self::maybeUpgradeIndexes($table);
        }
    }

    /**
     * Replace the old single-column `status` index with one that leads on
     * `created_at`.
     *
     * Every query against this table constrains a date range - the dashboard
     * counters pair it with a status, while the report chart, the day/time
     * heatmap and the pruning cron filter on the date alone. Leading with
     * `created_at` therefore serves all of them from one index. Leading with
     * `status` would only serve the first group, and barely: a log is
     * overwhelmingly 'sent', so that column narrows almost nothing.
     *
     * This only runs from migrate(), i.e. on activation and on new-site
     * creation - never on a normal page load. Reindexing a table that can hold
     * millions of rows is not free, so it must stay off the request path.
     *
     * @param string $table
     * @return void
     */
    private static function maybeUpgradeIndexes($table)
    {
        global $wpdb;

        if (!self::hasIndex($table, 'created_at_status')) {
            $wpdb->query("ALTER TABLE $table ADD INDEX `created_at_status` (`created_at`, `status`)");
        }

        /*
         * Dropped last, and only once the replacement is confirmed present, so
         * the table is never left with no index covering `status` at all.
         */
        if (self::hasIndex($table, 'status') && self::hasIndex($table, 'created_at_status')) {
            $wpdb->query("ALTER TABLE $table DROP INDEX `status`");
        }
    }

    /**
     * @param string $table
     * @param string $indexName
     * @return bool
     */
    private static function hasIndex($table, $indexName)
    {
        global $wpdb;

        // Table name is static/hard-coded, so wpdb->prepare() is sufficient
        return (bool)$wpdb->get_var(
            $wpdb->prepare("SHOW INDEX FROM $table WHERE Key_name = %s", $indexName)
        );
    }
}
