<?php namespace FluentSmtpDb\QueryBuilder\Adapters;

class Mysql extends BaseAdapter
{
    /**
     * @var string
     */
    protected $sanitizer = '`';
}
