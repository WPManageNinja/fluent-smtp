<?php defined('ABSPATH') or die;

/*
Plugin Name: Wp Fluent
Description: Wp Fluent WordPress Plugin
Version: 1.0.0
Author: 
Author URI: 
Plugin URI: 
License: GPLv2 or later
Text Domain: wpfluent
Domain Path: /resources/languages
*/

// Autoload plugin.
require_once(__DIR__.'/autoload.php');

if (! function_exists('wpFluent')) {
    /**
     * @return \WpFluent\QueryBuilder\QueryBuilderHandler
     */
    function wpFluent()
    {
        static $wpFluent;

        if (! $wpFluent) {
            global $wpdb;

            $connection = new \WpFluent\Connection($wpdb, ['prefix' => $wpdb->prefix]);

            $wpFluent = new \WpFluent\QueryBuilder\QueryBuilderHandler($connection);
        }

        return $wpFluent;
    }
}
