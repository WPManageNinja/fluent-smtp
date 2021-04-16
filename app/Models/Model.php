<?php

namespace FluentMail\App\Models;

class Model
{
    protected $db = null;
    protected $app = null;

    public function __construct()
    {
        $this->app = fluentMail();
        $this->db = $GLOBALS['wpdb'];
    }

    public function getTable()
    {
        return $this->table;
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->db, $method], $params);
    }

    public function getDb()
    {
        if(function_exists('wpFluent')) {
            return wpFluent();
        }

        static $wpFluent;

        if (! $wpFluent) {

            require_once(FLUENTMAIL_PLUGIN_PATH .'app/Services/wpfluent/autoload.php');
            global $wpdb;
            $connection = new \WpFluent\Connection($wpdb, ['prefix' => $wpdb->prefix]);

            $wpFluent = new \WpFluent\QueryBuilder\QueryBuilderHandler($connection);
        }

        return $wpFluent;

    }
}
