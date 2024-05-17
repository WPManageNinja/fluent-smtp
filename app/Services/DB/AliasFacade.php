<?php namespace FluentMail\App\Services\DB;

use FluentMail\App\Services\DB\QueryBuilder\QueryBuilderHandler;

/**
 * This class gives the ability to access non-static methods statically
 *
 * Class AliasFacade
 *
 * @package FluentAuthDb
 */
class AliasFacade
{

    /**
     * @var QueryBuilderHandler
     */
    protected static $queryBuilderInstance;

    /**
     * @param $method
     * @param $args
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        if (!static::$queryBuilderInstance) {
            static::$queryBuilderInstance = new QueryBuilderHandler();
        }

        // Call the non-static method from the class instance
        return call_user_func_array(array(static::$queryBuilderInstance, $method), $args);
    }

    /**
     * @param QueryBuilderHandler $queryBuilderInstance
     */
    public static function setQueryBuilderInstance($queryBuilderInstance)
    {
        static::$queryBuilderInstance = $queryBuilderInstance;
    }
}
