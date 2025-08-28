<?php namespace FluentMail\App\Services\DB\QueryBuilder;

use FluentMail\App\Services\DB\Connection;
use FluentMail\App\Services\DB\Exception;

class QueryBuilderHandler
{

    /**
     * @var \FluentMail\App\Services\DB\Viocon\Container
     */
    protected $container;

    /**
     * @var \FluentMail\App\Services\DB\src\Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $statements = array();

    /**
     * @var \wpdb
     */
    protected $db;

    /**
     * @var null|string
     */
    protected $dbStatement = null;

    /**
     * @var null|string
     */
    protected $tablePrefix = null;

    /**
     * @var \FluentMail\App\Services\DB\QueryBuilder\Adapters\BaseAdapter
     */
    protected $adapterInstance;

    /**
     * The PDO fetch parameters to use
     *
     * @var array
     */
    protected $fetchParameters = array(\PDO::FETCH_OBJ);
    /**
     * @var string
     */
    private $adapter;
    /**
     * @var array
     */
    private $adapterConfig;

    /**
     * @param null|\FluentMail\App\Services\DB\Connection $connection
     *
     * @throws \FluentMail\App\Services\DB\Exception
     */
    public function __construct(?Connection $connection = null)
    {
        if (is_null($connection)) {
            if (! $connection = Connection::getStoredConnection()) {
                throw new Exception('No database connection found.', 1);
            }
        }

        $this->connection = $connection;
        $this->container = $this->connection->getContainer();
        $this->db = $this->connection->getDbInstance();
        $this->adapter = $this->connection->getAdapter();
        $this->adapterConfig = $this->connection->getAdapterConfig();

        if (isset($this->adapterConfig['prefix'])) {
            $this->tablePrefix = $this->adapterConfig['prefix'];
        }
        // Query builder adapter instance
        $this->adapterInstance = $this->container->build(
            '\\FluentMail\\App\\Services\\DB\\QueryBuilder\\Adapters\\' . ucfirst($this->adapter),
            array($this->connection)
        );
    }

    /**
     * Set the fetch mode
     *
     * @param $mode
     * @return $this
     */
    public function setFetchMode($mode)
    {
        $this->fetchParameters = func_get_args();

        return $this;
    }

    /**
     * Fetch query results as object of specified type
     *
     * @param $className
     * @param array $constructorArgs
     * @return QueryBuilderHandler
     */
    public function asObject($className, $constructorArgs = array())
    {
        var_dump('need to implement this'); die();

        return $this->setFetchMode(\PDO::FETCH_CLASS, $className, $constructorArgs);
    }

    /**
     * @param null|\FluentMail\App\Services\DB\Connection $connection
     *
     * @return static
     */
    public function newQuery(?Connection $connection = null)
    {
        if (is_null($connection)) {
            $connection = $this->connection;
        }

        return new static($connection);
    }

    /**
     * @param       $sql
     * @param array $bindings
     *
     * @return $this
     */
    public function query($sql, $bindings = array())
    {
        $this->dbStatement = $this->container->build(
            '\\FluentMail\\App\\Services\\DB\\QueryBuilder\\QueryObject',
            array($sql, $bindings)
        )->getRawSql();

        return $this;
    }

    /**
     * @param $rawSql
     *
     * @return float execution time
     */
    public function statement($rawSql)
    {
        $start = microtime(true);

        $this->db->query($rawSql);

        return microtime(true) - $start;
    }

    /**
     * Get all rows
     *
     * @return array|object|null
     * @throws \FluentMail\App\Services\DB\Exception
     */
    public function get()
    {
        $eventResult = $this->fireEvents('before-select');

        if (! is_null($eventResult)) {
            return $eventResult;
        };

        if (is_null($this->dbStatement)) {
            $queryObject = $this->getQuery('select');

            $this->dbStatement = $queryObject->getRawSql();
        }

        $start = microtime(true);
        $result = $this->db->get_results($this->dbStatement);
        $executionTime = microtime(true) - $start;
        $this->dbStatement = null;
        $this->fireEvents('after-select', $result, $executionTime);

        return $result;
    }

    /**
     * Get first row
     *
     * @return \stdClass|null
     */
    public function first()
    {
        $this->limit(1);
        $result = $this->get();

        return empty($result) ? null : $result[0];
    }

    /**
     * @param        $value
     * @param string $fieldName
     *
     * @return null|\stdClass
     */
    public function findAll($fieldName, $value)
    {
        $this->where($fieldName, '=', $value);

        return $this->get();
    }

    /**
     * @param        $value
     * @param string $fieldName
     *
     * @return null|\stdClass
     */
    public function find($value, $fieldName = 'id')
    {
        $this->where($fieldName, '=', $value);

        return $this->first();
    }

    /**
     * Get count of rows
     *
     * @return int
     */
    public function count()
    {
        // Get the current statements
        $originalStatements = $this->statements;

        unset($this->statements['orderBys']);
        unset($this->statements['limit']);
        unset($this->statements['offset']);

        $count = $this->aggregate('count');
        $this->statements = $originalStatements;

        return $count;
    }

    /**
     * @param $type
     *
     * @return int
     */
    protected function aggregate($type)
    {
        // Get the current selects
        $mainSelects = isset($this->statements['selects']) ? $this->statements['selects'] : null;
        // Replace select with a scalar value like `count`
        $this->statements['selects'] = array($this->raw($type . '(*) as field'));
        $row = $this->get();

        // Set the select as it was
        if ($mainSelects) {
            $this->statements['selects'] = $mainSelects;
        } else {
            unset($this->statements['selects']);
        }

        if (($count = count($row)) > 1) {
            return $count;
        } else {
            $item = (array) $row[0];

            return (int) $item['field'];
        }
    }

    /**
     * @param string $type
     * @param array  $dataToBePassed
     *
     * @return mixed
     * @throws Exception
     */
    public function getQuery($type = 'select', $dataToBePassed = array())
    {
        $allowedTypes = array('select', 'insert', 'insertignore', 'replace', 'delete', 'update', 'criteriaonly');

        if (! in_array(strtolower($type), $allowedTypes)) {
            throw new Exception(wp_kses_post($type . ' is not a known type.'), 2);
        }

        $queryArr = $this->adapterInstance->$type($this->statements, $dataToBePassed);
        
        return  $this->container->build(
            '\\FluentMail\\App\\Services\\DB\\QueryBuilder\\QueryObject',
            array($queryArr['sql'], $queryArr['bindings'])
        );
    }

    /**
     * @param QueryBuilderHandler $queryBuilder
     * @param null                $alias
     *
     * @return Raw
     */
    public function subQuery(QueryBuilderHandler $queryBuilder, $alias = null)
    {
        $sql = '(' . $queryBuilder->getQuery()->getRawSql() . ')';

        if ($alias) {
            $sql = $sql . ' as ' . $alias;
        }

        return $queryBuilder->raw($sql);
    }

    /**
     * @param $data
     *
     * @return array|string
     * @throws \FluentMail\App\Services\DB\Exception
     */
    private function doInsert($data, $type)
    {
        $eventResult = $this->fireEvents('before-insert');

        if (! is_null($eventResult)) {
            return $eventResult;
        }

        // If first value is not an array
        // Its not a batch insert
        if (! is_array(current($data))) {
            $start = microtime(true);

            $queryObject = $this->getQuery($type, $data);

            $executionTime = $this->statement($queryObject->getRawSql());

            $return = $this->db->insert_id;
        } else {
            // Its a batch insert
            $executionTime = 0;
            $return = array();
            foreach ($data as $subData) {
                $start = microtime(true);

                $queryObject = $this->getQuery($type, $subData);

                $executionTime = $this->statement($queryObject->getRawSql());

                $return[] = $this->db->insert_id;
            }
        }

        $this->fireEvents('after-insert', $return, $executionTime);

        return $return;
    }

    /**
     * @param $data
     *
     * @return array|string
     */
    public function insert($data)
    {
        return $this->doInsert($data, 'insert');
    }

    /**
     * @param $data
     *
     * @return array|string
     */
    public function insertIgnore($data)
    {
        return $this->doInsert($data, 'insertignore');
    }

    /**
     * @param $data
     *
     * @return array|string
     */
    public function replace($data)
    {
        return $this->doInsert($data, 'replace');
    }

    /**
     * @param $data
     *
     * @throws \FluentMail\App\Services\DB\Exception
     */
    public function update($data)
    {
        $eventResult = $this->fireEvents('before-update');

        if (! is_null($eventResult)) {
            return $eventResult;
        }

        $queryObject = $this->getQuery('update', $data);

        $executionTime = $this->statement($queryObject->getRawSql());

        $this->fireEvents('after-update', $queryObject, $executionTime);
    }

    /**
     * @param $data
     *
     * @return array|string
     */
    public function updateOrInsert($data)
    {
        if ($this->first()) {
            return $this->update($data);
        } else {
            return $this->insert($data);
        }
    }

    /**
     * @param $data
     *
     * @return $this
     */
    public function onDuplicateKeyUpdate($data)
    {
        $this->addStatement('onduplicate', $data);

        return $this;
    }

    /**
     * @return mixed
     * @throws \FluentMail\App\Services\DB\Exception
     */
    public function delete()
    {
        $eventResult = $this->fireEvents('before-delete');

        if (! is_null($eventResult)) {
            return $eventResult;
        }

        $queryObject = $this->getQuery('delete');

        $executionTime = $this->statement($queryObject->getRawSql());

        $this->fireEvents('after-delete', $queryObject, $executionTime);
    }

    /**
     * @param string|array $tables Single table or multiple tables
     *                             as an array or as multiple parameters
     *
     * @return static
     */
    public function table($tables)
    {
        if (! is_array($tables)) {
            // because a single table is converted to an array anyways,
            // this makes sense.
            $tables = array($tables);
        }

        $instance = new static($this->connection);
        $tables = $this->addTablePrefix($tables, false);
        $instance->addStatement('tables', $tables);

        return $instance;
    }

    /**
     * @param $tables
     *
     * @return $this
     */
    public function from($tables)
    {
        if (! is_array($tables)) {
            $tables = array($tables);
        }

        $tables = $this->addTablePrefix($tables, false);
        $this->addStatement('tables', $tables);

        return $this;
    }

    /**
     * @param $fields
     *
     * @return $this
     */
    public function select($fields)
    {
        if (! is_array($fields)) {
            $fields = array($fields);
        }

        $fields = $this->addTablePrefix($fields);
        $this->addStatement('selects', $fields);

        return $this;
    }

    /**
     * @param $fields
     *
     * @return $this
     */
    public function selectDistinct($fields)
    {
        $this->select($fields);
        $this->addStatement('distinct', true);

        return $this;
    }

    /**
     * @param $field
     *
     * @return $this
     */
    public function groupBy($field)
    {
        $field = $this->addTablePrefix($field);
        $this->addStatement('groupBys', $field);

        return $this;
    }

    /**
     * @param        $fields
     * @param string $defaultDirection
     *
     * @return $this
     */
    public function orderBy($fields, $defaultDirection = 'ASC')
    {
        if (! is_array($fields)) {
            $fields = array($fields);
        }

        foreach ($fields as $key => $value) {
            $field = $key;
            $type = $value;

            if (is_int($key)) {
                $field = $value;
                $type = $defaultDirection;
            }

            if (!$field instanceof Raw) {
                $field = $this->addTablePrefix($field);
            }

            $this->statements['orderBys'][] = compact('field', 'type');
        }

        return $this;
    }

    /**
     * @param $limit
     *
     * @return $this
     */
    public function limit($limit)
    {
        $this->statements['limit'] = $limit;

        return $this;
    }

    /**
     * @param $offset
     *
     * @return $this
     */
    public function offset($offset)
    {
        $this->statements['offset'] = $offset;

        return $this;
    }

    /**
     * @param        $key
     * @param        $operator
     * @param        $value
     * @param string $joiner
     *
     * @return $this
     */
    public function having($key, $operator = null, $value = null, $joiner = 'AND')
    {
        $key = $this->addTablePrefix($key);
        $this->statements['havings'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }

    /**
     * @param        $key
     * @param        $operator
     * @param        $value
     *
     * @return $this
     */
    public function orHaving($key, $operator, $value)
    {
        return $this->having($key, $operator, $value, 'OR');
    }

    /**
     * @param $key
     * @param $operator
     * @param $value
     *
     * @return $this
     */
    public function where($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value);
    }

    /**
     * @param $key
     * @param $operator
     * @param $value
     *
     * @return $this
     */
    public function orWhere($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR');
    }

    /**
     * @param $key
     * @param $operator
     * @param $value
     *
     * @return $this
     */
    public function whereNot($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'AND NOT');
    }

    /**
     * @param $key
     * @param $operator
     * @param $value
     *
     * @return $this
     */
    public function orWhereNot($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR NOT');
    }

    /**
     * @param       $key
     * @param array $values
     *
     * @return $this
     */
    public function whereIn($key, $values)
    {
        return $this->whereHandler($key, 'IN', $values, 'AND');
    }

    /**
     * @param       $key
     * @param array $values
     *
     * @return $this
     */
    public function whereNotIn($key, $values)
    {
        return $this->whereHandler($key, 'NOT IN', $values, 'AND');
    }

    /**
     * @param       $key
     * @param array $values
     *
     * @return $this
     */
    public function orWhereIn($key, $values)
    {
        return $this->whereHandler($key, 'IN', $values, 'OR');
    }

    /**
     * @param       $key
     * @param array $values
     *
     * @return $this
     */
    public function orWhereNotIn($key, $values)
    {
        return $this->whereHandler($key, 'NOT IN', $values, 'OR');
    }

    /**
     * @param $key
     * @param $valueFrom
     * @param $valueTo
     *
     * @return $this
     */
    public function whereBetween($key, $valueFrom, $valueTo)
    {
        return $this->whereHandler($key, 'BETWEEN', array($valueFrom, $valueTo), 'AND');
    }

    /**
     * @param $key
     * @param $valueFrom
     * @param $valueTo
     *
     * @return $this
     */
    public function orWhereBetween($key, $valueFrom, $valueTo)
    {
        return $this->whereHandler($key, 'BETWEEN', array($valueFrom, $valueTo), 'OR');
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function whereNull($key)
    {
        return $this->whereNullHandler($key);
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function whereNotNull($key)
    {
        return $this->whereNullHandler($key, 'NOT');
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function orWhereNull($key)
    {
        return $this->whereNullHandler($key, '', 'or');
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function orWhereNotNull($key)
    {
        return $this->whereNullHandler($key, 'NOT', 'or');
    }

    protected function whereNullHandler($key, $prefix = '', $operator = '')
    {
        $key = $this->adapterInstance->wrapSanitizer($this->addTablePrefix($key));

        return $this->{$operator . 'Where'}($this->raw("{$key} IS {$prefix} NULL"));
    }

    /**
     * @param        $table
     * @param        $key
     * @param        $operator
     * @param        $value
     * @param string $type
     *
     * @return $this
     */
    public function join($table, $key, $operator = null, $value = null, $type = 'inner')
    {
        if (! $key instanceof \Closure) {
            $key = function ($joinBuilder) use ($key, $operator, $value) {
                $joinBuilder->on($key, $operator, $value);
            };
        }

        // Build a new JoinBuilder class, keep it by reference so any changes made
        // in the closure should reflect here
        $joinBuilder = $this->container->build('\\FluentMail\\App\\Services\\DB\\QueryBuilder\\JoinBuilder', array($this->connection));
        $joinBuilder = & $joinBuilder;
        // Call the closure with our new joinBuilder object
        $key($joinBuilder);
        $table = $this->addTablePrefix($table, false);
        // Get the criteria only query from the joinBuilder object
        $this->statements['joins'][] = compact('type', 'table', 'joinBuilder');

        return $this;
    }

    /**
     * Runs a transaction
     *
     * @param $callback
     *
     * @return $this
     */
    public function transaction(\Closure $callback)
    {
        try {
            // Begin the PDO transaction
            $this->db->query('START TRANSACTION');

            // Get the Transaction class
            $transaction = $this->container->build(
                '\\FluentMail\\App\\Services\\DB\\QueryBuilder\\Transaction',
                array($this->connection)
            );

            // Call closure
            $callback($transaction);

            // If no errors have been thrown or the transaction wasn't completed within
            // the closure, commit the changes
            $this->db->query('COMMIT');

            return $this;
        } catch (TransactionHaltException $e) {
            // Commit or rollback behavior has been handled in the closure, so exit
            return $this;
        } catch (\Exception $e) {
            // something happened, rollback changes
            $this->db->query('ROLLBACK');

            return $this;
        }
    }

    /**
     * @param      $table
     * @param      $key
     * @param null $operator
     * @param null $value
     *
     * @return $this
     */
    public function leftJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'left');
    }

    /**
     * @param      $table
     * @param      $key
     * @param null $operator
     * @param null $value
     *
     * @return $this
     */
    public function rightJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'right');
    }

    /**
     * @param      $table
     * @param      $key
     * @param null $operator
     * @param null $value
     *
     * @return $this
     */
    public function innerJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'inner');
    }

    /**
     * Add a raw query
     *
     * @param $value
     * @param $bindings
     *
     * @return mixed
     */
    public function raw($value, $bindings = array())
    {
        return $this->container->build('\\FluentMail\\App\\Services\\DB\\QueryBuilder\\Raw', array($value, $bindings));
    }

    /**
     * Return db instance
     *
     * @return \wpdb
     */
    public function db()
    {
        return $this->db;
    }

    /**
     * @param \FluentMail\App\Services\DB\Connection $connection
     *
     * @return $this
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @return \FluentMail\App\Services\DB\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param        $key
     * @param        $operator
     * @param        $value
     * @param string $joiner
     *
     * @return $this
     */
    protected function whereHandler($key, $operator = null, $value = null, $joiner = 'AND')
    {
        $key = $this->addTablePrefix($key);
        $this->statements['wheres'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }

    /**
     * Add table prefix (if given) on given string.
     *
     * @param      $values
     * @param bool $tableFieldMix If we have mixes of field and table names with a "."
     *
     * @return array|mixed
     */
    public function addTablePrefix($values, $tableFieldMix = true)
    {
        if (is_null($this->tablePrefix)) {
            return $values;
        }

        // $value will be an array and we will add prefix to all table names

        // If supplied value is not an array then make it one
        $single = false;

        if (! is_array($values)) {
            $values = array($values);
            // We had single value, so should return a single value
            $single = true;
        }

        $return = array();

        foreach ($values as $key => $value) {
            // It's a raw query, just add it to our return array and continue next
            if ($value instanceof Raw || $value instanceof \Closure) {
                $return[$key] = $value;
                continue;
            }

            // If key is not integer, it is likely a alias mapping,
            // so we need to change prefix target
            $target = &$value;
            if (! is_int($key)) {
                $target = &$key;
            }

            if (! $tableFieldMix || ($tableFieldMix && strpos($target, '.') !== false)) {
                $target = $this->tablePrefix . $target;
            }

            $return[$key] = $value;
        }

        // If we had single value then we should return a single value (end value of the array)
        return $single ? end($return) : $return;
    }

    /**
     * @param $key
     * @param $value
     */
    protected function addStatement($key, $value)
    {
        if (! is_array($value)) {
            $value = array($value);
        }

        if (! array_key_exists($key, $this->statements)) {
            $this->statements[$key] = $value;
        } else {
            $this->statements[$key] = array_merge($this->statements[$key], $value);
        }
    }

    /**
     * @param $event
     * @param $table
     *
     * @return callable|null
     */
    public function getEvent($event, $table = ':any')
    {
        return $this->connection->getEventHandler()->getEvent($event, $table);
    }

    /**
     * @param          $event
     * @param string   $table
     * @param callable $action
     *
     * @return void
     */
    public function registerEvent($event, $table, \Closure $action)
    {
        $table = $table ?: ':any';

        if ($table != ':any') {
            $table = $this->addTablePrefix($table, false);
        }

        $this->connection->getEventHandler()->registerEvent($event, $table, $action);
    }

    /**
     * @param          $event
     * @param string   $table
     *
     * @return void
     */
    public function removeEvent($event, $table = ':any')
    {
        if ($table != ':any') {
            $table = $this->addTablePrefix($table, false);
        }

        $this->connection->getEventHandler()->removeEvent($event, $table);
    }

    /**
     * @param      $event
     * @return mixed
     */
    public function fireEvents($event)
    {
        $params = func_get_args();
        array_unshift($params, $this);

        return call_user_func_array(
            array($this->connection->getEventHandler(), 'fireEvents'),
            $params
        );
    }

    /**
     * @return array
     */
    public function getStatements()
    {
        return $this->statements;
    }

    /**
     * Get the paginated rows.
     *
     * @param null $perPage
     * @param array $columns
     *
     * @return array
     */
    public function paginate($perPage = null, $columns = array('*'))
    {
        $currentPage = intval($_GET['page']) ?: 1;

        $perPage = $perPage ?: intval($_REQUEST['per_page']) ?: 15;

        $skip = $perPage * ($currentPage - 1);

        $data = (array) $this->select($columns)->limit($perPage)->offset($skip)->get();

        $dataCount = count($data);

        $from = $dataCount > 0 ? ($currentPage - 1) * $perPage + 1 : null;

        $to = $dataCount > 0 ? $from + $dataCount - 1 : null;

        $total = $this->count();

        $lastPage = (int) ceil($total / $perPage);

        return array(
            'current_page'  => $currentPage,
            'per_page'      => $perPage,
            'from'          => $from,
            'to'            => $to,
            'last_page'     => $lastPage,
            'total'         => $total,
            'data'          => $data,
        );
    }

    /**
     * Apply the callback's query changes if the given "value" is true.
     *
     * @param mixed $value
     * @param callable $callback
     * @param callable $default
     * @return mixed
     */
    public function when($value, $callback, $default = null)
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }
}
