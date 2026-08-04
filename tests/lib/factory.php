<?php
/**
 * Exact, database-backed fixtures for FluentSMTP integration tests.
 *
 * The production log table is never used for behavioral assertions. Tests get
 * isolated InnoDB tables and point a real Logger model at them via reflection.
 */

class FsmtpFactory
{
    /** @var array<int,string> */
    private static $tables = [];

    /** Create the minimum shipped 2.3.0 email-log table shape. */
    public static function emailLogTable($statusIndex = true, $compositeIndex = false)
    {
        global $wpdb;

        $suffix = strtolower(wp_generate_password(10, false, false));
        $table = $wpdb->prefix . 'fsmtp_test_' . preg_replace('/[^a-z0-9_]/', '', $suffix);
        self::assertIdentifier($table);

        $indexes = [];
        if ($statusIndex) {
            $indexes[] = 'INDEX `status` (`status`)';
        }
        if ($compositeIndex) {
            $indexes[] = 'INDEX `created_at_status` (`created_at`, `status`)';
        }
        $indexSql = $indexes ? ",\n            " . implode(",\n            ", $indexes) : '';

        $sql = "CREATE TABLE `{$table}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `site_id` INT UNSIGNED NULL,
            `to` VARCHAR(255) NULL,
            `from` VARCHAR(255) NULL,
            `subject` VARCHAR(255) NULL,
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
            PRIMARY KEY (`id`){$indexSql}
        ) ENGINE=InnoDB";

        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Could not create test log table: ' . $wpdb->last_error);
        }

        self::$tables[] = $table;
        return $table;
    }

    /** Insert one row and return its exact ID. */
    public static function insertLog($table, array $values)
    {
        global $wpdb;
        self::assertIdentifier($table);

        $defaults = [
            'to'         => 'recipient@example.test',
            'from'       => 'sender@example.test',
            'subject'    => FsmtpTest::uniq('fixture'),
            'status'     => 'sent',
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ];

        if ($wpdb->insert($table, array_merge($defaults, $values)) === false) {
            throw new RuntimeException('Could not insert test log: ' . $wpdb->last_error);
        }
        return (int) $wpdb->insert_id;
    }

    /** Point a real Logger instance at an isolated test table. */
    public static function loggerForTable($table)
    {
        self::assertIdentifier($table);
        $logger = new \FluentMail\App\Models\Logger();
        $property = new ReflectionProperty($logger, 'table');
        $property->setAccessible(true);
        $property->setValue($logger, $table);
        return $logger;
    }

    /**
     * Redirect production log-table SQL to an isolated real table. This keeps
     * hard-coded reporting/controller paths database-backed without allowing a
     * behavioral test to read, insert, update, or delete a production log row.
     */
    public static function productionLogTableRedirect($table)
    {
        global $wpdb;
        self::assertIdentifier($table);
        $productionTable = $wpdb->prefix . FLUENT_MAIL_DB_PREFIX . 'email_logs';

        return function ($query) use ($productionTable, $table) {
            return str_replace($productionTable, $table, $query);
        };
    }

    /** @return array<string,array<int,string>> index name => ordered columns */
    public static function indexes($table)
    {
        global $wpdb;
        self::assertIdentifier($table);

        $rows = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
        $indexes = [];
        foreach ($rows as $row) {
            $indexes[$row['Key_name']][(int) $row['Seq_in_index']] = $row['Column_name'];
        }
        foreach ($indexes as &$columns) {
            ksort($columns);
            $columns = array_values($columns);
        }
        unset($columns);
        return $indexes;
    }

    public static function dropTable($table)
    {
        global $wpdb;
        self::assertIdentifier($table);
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        self::$tables = array_values(array_filter(self::$tables, function ($candidate) use ($table) {
            return $candidate !== $table;
        }));
    }

    /** Exact cleanup; safe and intentionally idempotent. */
    public static function cleanup()
    {
        foreach (array_reverse(self::$tables) as $table) {
            self::dropTable($table);
        }
        self::$tables = [];
    }

    private static function assertIdentifier($identifier)
    {
        if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Unsafe test-table identifier.');
        }
    }
}
