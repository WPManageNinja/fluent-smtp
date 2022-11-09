<?php defined('ABSPATH') or die;

// Autoload plugin.
require_once(__DIR__.'/autoload.php');

if (! function_exists('FluentSmtpDb')) {
    /**
     * @return \FluentSmtpDb\QueryBuilder\QueryBuilderHandler
     */
    function FluentSmtpDb()
    {
        static $FluentSmtpDb;

        if (! $FluentSmtpDb) {
            global $wpdb;

            $connection = new \FluentSmtpDb\Connection($wpdb, ['prefix' => $wpdb->prefix]);

            $FluentSmtpDb = new \FluentSmtpDb\QueryBuilder\QueryBuilderHandler($connection);
        }

        return $FluentSmtpDb;
    }
}
