<?php

namespace FluentMail\App\Models;

class Model
{
    protected $db  = null;
    protected $app = null;

    public function __construct()
    {
        $this->app = fluentMail();
        $this->db  = $GLOBALS['wpdb'];
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
        return fluentMailDb();
    }
}
