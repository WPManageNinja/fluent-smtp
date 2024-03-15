<?php defined('ABSPATH') or die;


if (! function_exists('FluentSmtpDb')) {
    /**
     * @return \FluentMail\App\Services\DB\QueryBuilder\QueryBuilderHandler
     */
    function FluentSmtpDb()
    {
        static $FluentSmtpDb;

        if (! $FluentSmtpDb) {
            global $wpdb;

            $connection = new \FluentMail\App\Services\DB\Connection($wpdb, ['prefix' => $wpdb->prefix]);

            $FluentSmtpDb = new \FluentMail\App\Services\DB\QueryBuilder\QueryBuilderHandler($connection);
        }

        return $FluentSmtpDb;
    }
}
