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

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
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
                `updated_at` TIMESTAMP NULL
            ) $charsetCollate;";

            dbDelta($sql);
        }
    }
}
