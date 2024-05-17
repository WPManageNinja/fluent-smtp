<?php
namespace FluentMail\App\Services\DB;

use FluentMail\App\Services\DB\Viocon\Container;

class Connection
{

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var string
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $adapterConfig;

    /**
     * @var \wpdb $wpdb
     */
    protected $dbInstance;

    /**
     * @var \wpdb $wpdb
     */
    protected $wpdb;

    /**
     * @var Connection
     */
    protected static $storedConnection;

    /**
     * @var EventHandler
     */
    protected $eventHandler;

    /**
     * @param                $wpdb
     * @param array          $adapterConfig
     * @param null|string    $alias
     * @param null|Container $container
     */
    public function __construct($wpdb, array $config = array(), $alias = null, Container $container = null)
    {
        $container = $container ? : new Container();

        $this->container = $container;

        $this->wpdb = $wpdb;

        $this->setAdapter()->setAdapterConfig($config)->connect();

        // Create event dependency
        $this->eventHandler = $this->container->build('\\FluentMail\\App\\Services\\DB\\EventHandler');

        if ($alias) {
            $this->createAlias($alias);
        }
    }

    /**
     * Create an easily accessible query builder alias
     *
     * @param $alias
     */
    public function createAlias($alias)
    {
        class_alias('FluentMail\\App\\Services\\DB\\AliasFacade', $alias);

        $builder = $this->container->build('\\FluentMail\\App\\Services\\DB\\QueryBuilder\\QueryBuilderHandler', array($this));

        AliasFacade::setQueryBuilderInstance($builder);
    }

    /**
     * Returns an instance of Query Builder
     */
    public function getQueryBuilder()
    {
        return $this->container->build('\\FluentMail\\App\\Services\\DB\\QueryBuilder\\QueryBuilderHandler', array($this));
    }


    /**
     * Create the connection adapter
     */
    protected function connect()
    {
        $this->setDbInstance($this->wpdb);

        // Preserve the first database connection with a static property
        if (! static::$storedConnection) {
            static::$storedConnection = $this;
        }
    }

    /**
     * @param $db
     *
     * @return $this
     */
    public function setDbInstance($db)
    {
        $this->dbInstance = $db;

        return $this;
    }

    /**
     * @return \wpdb
     */
    public function getDbInstance()
    {
        return $this->dbInstance;
    }

    /**
     * @param $adapter
     *
     * @return $this
     */
    public function setAdapter($adapter = 'mysql')
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @return string
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param array $adapterConfig
     *
     * @return $this
     */
    public function setAdapterConfig(array $adapterConfig)
    {
        $this->adapterConfig = $adapterConfig;

        return $this;
    }

    /**
     * @return array
     */
    public function getAdapterConfig()
    {
        return $this->adapterConfig;
    }

    /**
     * @return \FluentMail\App\Services\DB\Viocon\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @return EventHandler
     */
    public function getEventHandler()
    {
        return $this->eventHandler;
    }

    /**
     * @return Connection
     */
    public static function getStoredConnection()
    {
        return static::$storedConnection;
    }
}
