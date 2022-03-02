<?php
namespace Tk\Db;

use Tk\Callback;
use Tk\Db\Exception;

/**
 * PDO Database driver
 *
 * @author Tropotek <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @author Patrick S Scott<lazeras@kaoses.com>
 * @see http://www.kaoses.com
 * @license Copyright 2007 Tropotek
 */
class Pdo extends \PDO
{

    /**
     * The key for the option to enable ANSI mode for MySQL
     */
    const ANSI_QUOTES = 'mysql.ansi.quotes';

    /**
     * @var array
     */
    public static $instance = array();

    /**
     * @var bool
     */
    public static $logLastQuery = true;

    /**
     * @var bool
     */
    public static $PDO_TIMEOUT = 30;

    /**
     * @var string
     */
    protected $parameterQuote = '';

    /**
     * Variable to count the transaction int
     * @var int
     */
    protected $transactionCounter = 0;

    /**
     * The query log array.
     * @var array
     */
    private $log = array();

    /**
     * The query time in seconds
     * @var int
     */
    public $queryTime = 0;

    /**
     * The total query time in seconds
     * @var int
     */
    public $totalQueryTime = 0;

    /**
     * @var string
     */
    public $lastQuery = '';

    /**
     * @var string
     */
    public $dbName = '';

    /**
     * @var string
     */
    public $driver = '';

    /**
     * @var Callback
     */
    private $onLogListener;

    /**
     * @var array
     */
    private $options = array();


    /**
     * Construct a \PDO SQL driver object
     *
     * Added options:
     *
     *  o $options['mysql.ansi.quotes'] = true; // Change to true to force MySQL to use ANSI quoting style.
     *  o $options['timezone'] = '';
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     * @throws \Exception
     */
    public function __construct($dsn, $username, $password, $options = array())
    {
        $this->onLogListener = Callback::create();
        $options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
        //vd($dsn, $username, $password, $options);
        parent::__construct($dsn, $username, $password, $options);
        $this->options = $options;
        $this->options['user'] = $username;
        $this->options['pass'] = $password;

        //$this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array(\Tk\Db\PdoStatement::class, array($this))); // Not compat with PHP 5.3
        $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('\Tk\Db\PdoStatement', array($this)));

        $regs = array();
        preg_match('/^([a-z]+):(([a-z]+)=([a-z0-9_-]+))+/i', $dsn, $regs);
        $this->dbName = $regs[4];

        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(\PDO::ATTR_TIMEOUT, self::$PDO_TIMEOUT);

        // Get mysql to emulate standard DB's
        self::$logLastQuery = false;
        if ($this->getDriver() == 'mysql') {
            $version = $this->query('select version()')->fetchColumn();
            $version = (float)mb_substr($version, 0, 6);
            if ($version < '5.5.3') {
                $this->exec('SET CHARACTER SET utf8;');
                $this->exec('ALTER DATABASE CHARACTER SET utf8 COLLATE utf8_unicode_ci;');
            } else {
                $this->exec('SET CHARACTER SET utf8mb4;');
                $this->exec('ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
            }

            if (isset($options['timezone'])) {
                $this->exec('SET time_zone = \'' . $options['timezone'] . '\'');
            }
            if (isset($options[self::ANSI_QUOTES]) && $options[self::ANSI_QUOTES] == true) {
                $this->exec("SET SESSION sql_mode = 'ANSI_QUOTES'");
            }
            $this->parameterQuote = '`';
        } else {
            if (isset($options['timezone'])) {
                $this->exec('SET TIME ZONE \'' . $options['timezone'] . '\'');
            }
            $this->parameterQuote = '"';
        }
        self::$logLastQuery = true;
    }

    /**
     * Call this to create/get a DB instance
     *
     * $options = array(
     *   'type' => 'mysql',
     *   'host' => 'localhost',
     *   'name' => 'database',
     *   'user' => 'user',
     *   'pass' => 'pass',
     *   'timezone' => '',              // optional
     *   'mysql.ansi.quotes' => true   // optional
     * );
     *
     * Different database instances are stored in an array by the $name key
     *
     * ON the first call supply the connection params in the options as
     * outlined, then subsequent calls can be made with no params or just the name
     * param as required.
     *
     * When calling this if only the options array is sent in place of the name value
     * then the 'default' value is used for the name, therefore:
     *   Pdo::getInstance($options) is a valid call
     *
     * @param string|array $name (Optional)
     * @param array $options
     * @return Pdo|null
     * @throws \Exception
     * @tot Not secure putting the DB login details within the object
     */
    public static function getInstance($name = '', $options = array())
    {
        // return the first available DB connection if no params
        if (!$name && !count($options) && count(self::$instance)) {
            return current(self::$instance);
        }
        if (!isset(self::$instance[$name])) {
            self::$instance[$name] = static::create($options);
            return self::$instance[$name];
        }
        return self::$instance[$name];
    }

    /**
     * Call this to create a new DB instance
     *
     * $options = array(
     *   'type' => 'mysql',
     *   'host' => 'localhost',
     *   'name' => 'database',
     *   'user' => 'user',
     *   'pass' => 'pass',
     *   'timezone' => '',              // optional
     *   'mysql.ansi.quotes' => true   // optional
     * );
     * @param $options
     * @return Pdo
     * @throws \Exception
     */
    public static function create($options)
    {
        $dsn = $options['type'] . ':dbname=' . $options['name'] . ';host=' . $options['host'];
        $db = new self($dsn, $options['user'], $options['pass'], $options);
        return $db;
    }


    /**
     * Method to return an array of connection attributes.
     *
     * @see http://www.php.net/manual/en/pdo.getattribute.php Pdo getAttribute
     *
     * @param array $attributes
     * @return array $return
     */
    public function getConnectionParameters($attributes = array("DRIVER_NAME", "AUTOCOMMIT", "ERRMODE", "CLIENT_VERSION",
        "CONNECTION_STATUS", "PERSISTENT", "SERVER_INFO", "SERVER_VERSION"))
    {
        $return = array();
        foreach ($attributes as $val) {
            try {
                $return["PDO::ATTR_$val"] = $this->getAttribute(constant("PDO::ATTR_$val")) . "\n";
            } catch (\Exception $e) { }
        }
        return $return;
    }

    /**
     * Return an option that was sent to the DB on creation
     *
     * @param $k
     * @return mixed|string
     */
    public function getOption($k)
    {
        if (isset($this->options[$k]))
            return $this->options[$k];
        return '';
    }


    /**
     * Get the driver name
     *
     * @return string
     */
    public function getDriver()
    {
        return $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }


    /**
     * get the selected DB name
     *
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->dbName;
    }

    /**
     * @return Callback
     */
    public function getOnLogListener()
    {
        return $this->onLogListener;
    }

    /**
     * @param callable $observer The observer.
     * @deprecated use addOnLogListener
     */
    public function setOnLogListener($observer)
    {
        $this->onLogListener = $observer;
    }

    /**
     * Eg: function (array $entry) {}
     *
     * @param callable $callable
     * @param int $priority
     */
    public function addOnLogListener($callable, $priority = Callback::DEFAULT_PRIORITY)
    {
        $this->getOnLogListener()->append($callable, $priority);
    }

    protected $logEnabled = true;

    /**
     * @return bool
     */
    public function isLogEnabled(): bool
    {
        return $this->logEnabled;
    }

    /**
     * @param bool $logEnabled
     * @return Pdo
     */
    public function setLogEnabled(bool $logEnabled): Pdo
    {
        $this->logEnabled = $logEnabled;
        return $this;
    }

    /**
     * Adds an array entry to the log.
     *
     * @param array $entry The log entry.
     */
    public function addLog(array $entry)
    {
        if ($this->isLogEnabled()) {
            $this->log[] = $entry;
            $this->getOnLogListener()->execute($entry);
        }
    }

    /**
     * Clears the log.
     * // Damn this uses a lot of mem?????
     * TODO: I think we need to look into removing this log as it can use a lot of unnecessary memory
     */
    public function clearLog()
    {
        $this->log = array();
    }

    /**
     * Returns the log.
     *
     * @return mixed
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Returns the last array entry of the log
     *
     * @return mixed
     */
    public function getLastLog()
    {
        return end($this->log);
    }

    /**
     *
     * @param string $sql
     *
     * @return self
     */
    public function setLastQuery($sql)
    {
        if (self::$logLastQuery)
            $this->lastQuery = $sql;

        return $this;
    }

    /**
     * Get the last executed query.
     *
     * @return string
     */
    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @see \PDO::prepare()
     * @see http://www.php.net/manual/en/pdo.prepare.php
     * @param $statement
     * @param array $driver_options
     * @return  PDOStatement|\PDOStatement
     * @throws \PDOException
     */
    public function prepare($statement, $driver_options = array())
    {
        $result = parent::prepare($statement, $driver_options);
        return $result;
    }

    /**
     * Execute an SQL statement and return the number of affected rows
     *
     * @see \PDO::exec()
     * @see http://www.php.net/manual/en/pdo.exec.php
     * @param string $statement The SQL statement to prepare and execute
     * @return PDOStatement|int
     * @throws \Tk\Db\Exception
     */
    public function exec($statement)
    {
        $this->setLastQuery($statement);
        $start = microtime(true);

        try {
            $result = parent::exec($statement);
        } catch (\Exception $e) {
            $info = $this->errorInfo();
            throw new Exception(end($info), $e->getCode(), $e, $statement);
        }

        if ($result === false) {
            $info = $this->errorInfo();
            throw new Exception(end($info), $this->errorCode(), null, $statement);
        }
        $this->addLog(
            array(
                'query' => $statement,
                'time' => microtime(true) - $start,
                'values' => array(),
            )
        );

        return $result;
    }


    /**
     * @param $name
     * @param $arguments
     */
//    public function __call($name, $arguments)
//    {
//        vd($name);
//        // NOTE: this is to avoid the query() function inheritance issues with PHP7.4+
//        if ($name = 'query') {
//            vd();
//            return call_user_func_array(array($this, 'tkQuery'), $arguments);
//        }
//    }


    /**
     * NOTICE FOR PHP 8.0:
     *
     * We will have to work out a way to use this in the future as the PDO::query override change with versions and is
     * bad, alternatively we should stop inheriting the PDO object and make it an instance variable That wew call..
     *
     *  
     *
     * @param $statement
     * @param int $mode
     * @param null $arg3
     * @param array $ctorargs
     * @return mixed
     */
    public function tkQuery($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = array())
    {
        return call_user_func_array(array($this, 'query'), func_get_args());
    }


    /**
     * Executes an SQL statement, returning a result set as a PDOStatement object
     *
     * @see \PDO::query()
     * @see http://au2.php.net/pdo.query
     *
     * @param string $statement
     * @param int $mode The fetch mode must be one of the PDO::FETCH_* constants.
     * @param mixed $arg3 The second and following parameters are the same as the parameters for PDOStatement::setFetchMode.
     * @param array $ctorargs
     * @return PDOStatement \PDO::query() returns a PDOStatement object, or FALSE on failure.
     * @throws \Tk\Db\Exception
     */
    //public function tkQuery($statement, $mode =
    // PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = array())
    //public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = array())
    public function query(string $statement, ?int $mode = PDO::ATTR_DEFAULT_FETCH_MODE, mixed ...$fetchModeArgs)
    {
        $this->setLastQuery($statement);
        $start = microtime(true);
        try {
            $result = call_user_func_array(array('parent', 'query'), func_get_args());
            if ($result === false) {
                $info = $this->errorInfo();
                throw new Exception(end($info), $this->errorCode(), null, $statement);
            }
        } catch (\Exception $e) {
            $info = $this->errorInfo();
            throw new Exception(end($info), $e->getCode(), $e, $statement);
        }
        $this->addLog(
            array(
                'query' => $statement,
                'time' => microtime(true) - $start,
                'values' => array(),
            )
        );
        return $result;
    }

    /**
     *  Initiates a transaction
     *
     * @see PDO::beginTransaction()
     * @see http://php.net/manual/en/pdo.begintransaction.php#90239 SqlLite implementation
     * @see http://www.php.net/manual/en/pdo.begintransaction.php
     * @return bool
     */
    function beginTransaction()
    {
        if (!$this->transactionCounter++)
            return parent::beginTransaction();

        return $this->transactionCounter >= 0;
    }

    /**
     * Commits a transaction
     *
     * @see PDO::commit()
     * @see http://www.php.net/manual/en/pdo.commit.php
     * @return bool
     */
    public function commit()
    {
        if (!--$this->transactionCounter)
            return parent::commit();

        return $this->transactionCounter >= 0;
    }

    /**
     * Rolls back a transaction
     *
     * @see PDO::rollback()
     * @see http://www.php.net/manual/en/pdo.rollback.php
     * @return bool
     */
    public function rollback()
    {
        if ($this->transactionCounter >= 0) {
            $this->transactionCounter = 0;

            return parent::rollBack();
        }
        $this->transactionCounter = 0;

        return false;
    }


    /**
     * Count a query and return the total possible results
     *
     * @param string $sql
     * @return int
     * @throws \Tk\Db\Exception
     */
    public function countFoundRows($sql = '')
    {
        if (!$sql) $sql = $this->getLastQuery();
        if (!$sql) return 0;

        self::$logLastQuery = false;
        $total = 0;
        if ($this->getDriver() == 'mysql' && preg_match('/^SELECT SQL_CALC_FOUND_ROWS/i', $sql)) {   // Mysql only
            $countSql = 'SELECT FOUND_ROWS()';
            $result = $this->query($countSql);
            if ($result === false) {
                $info = $this->errorInfo();
                throw new Exception(end($info));
            }
            $result->setFetchMode(\PDO::FETCH_ASSOC);
            $row = $result->fetch();
            if ($row) {
                $total = (int) $row['FOUND_ROWS()'];
            }
        } else if (preg_match('/^SELECT/i', $sql)) {
            $cSql = preg_replace('/(LIMIT [0-9]+(( )?,?( )?(OFFSET )?[0-9]+)?)?/i', '', $sql);
            $countSql = "SELECT COUNT(*) as i FROM ($cSql) as t";
            $result = $this->query($countSql);
            if ($result === false) {
                $info = $this->errorInfo();
                throw new Exception(end($info));
            }
            $result->setFetchMode(\PDO::FETCH_ASSOC);
            $row = $result->fetch();
            if ($row) {
                $total = (int) $row['i'];
            }
        }
        self::$logLastQuery = true;
        return $total;
    }

    /**
     * Check if a database with the supplied name exists
     *
     * @param string $dbName
     * @return bool
     * @throws \Tk\Db\Exception
     * @deprecated [2.0.15] use hasDatabase()
     */
    public function databaseExists($dbName)
    {
        return $this->hasDatabase($dbName);
    }

    /**
     * Check if a database with the supplied name exists
     *
     * @param string $dbName
     * @return bool
     * @throws \Tk\Db\Exception
     * @version 2.0.15
     */
    public function hasDatabase($dbName)
    {
        $list = $this->getDatabaseList();
        return in_array($dbName, $list);
    }

    /**
     * Check if a table exists in the current database
     *
     * @param string $table
     * @return bool
     * @throws \Tk\Db\Exception
     * @deprecated [2.0.15] use hasTable()
     */
    public function tableExists($table)
    {
        return $this->hasTable($table);
    }

    /**
     * Check if a table exists in the current database
     *
     * @param string $table
     * @return bool
     * @throws \Tk\Db\Exception
     * @version 2.0.15
     */
    public function hasTable($table)
    {
        $list = $this->getTableList();
        return in_array($table, $list);
    }

    /**
     * Get an array containing all the available databases to the user
     *
     * @return array
     * @throws \Tk\Db\Exception
     */
    public function getDatabaseList()
    {
        $result = null;
        $list = array();
        if ($this->getDriver() == 'mysql') {
            $sql = 'SHOW DATABASES';
            $result = $this->query($sql);
//            $result->setFetchMode(\PDO::FETCH_ASSOC);
//            foreach ($result as $row) {
//                $list[] = $row['Database'];
//            }
        } else if ($this->getDriver() == 'pgsql') {
            $sql = sprintf('SELECT datname FROM pg_database WHERE datistemplate = false');
            $result = $this->query($sql);
//            $result->setFetchMode(\PDO::FETCH_ASSOC);
//            foreach ($result as $row) {
//                $list[] = $row['datname'];
//            }
        }
        if ($result) {
            $list = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
        }
        return $list;
    }

    /**
     * Get an array containing all the table names for this DB
     *
     * @return array
     * @throws \Tk\Db\Exception
     */
    public function getTableList()
    {
        self::$logLastQuery = false;
        $result = null;
        $list = array();
        if ($this->getDriver() == 'mysql') {
            $sql = 'SHOW TABLES';
            $result = $this->query($sql);
//            $list = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
//            $result->setFetchMode(\PDO::FETCH_NUM);
//            foreach ($result as $row) {
//                $list[] = $row[0];
//            }
        } else if ($this->getDriver() == 'pgsql') {
            $sql = sprintf('SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\'');
            $result = $this->query($sql);
//            $list = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
//            $result->setFetchMode(\PDO::FETCH_NUM);
//            foreach ($result as $row) {
//                $list[] = $row[0];
//            }
        }
        if ($result) {
            $list = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
        }
        self::$logLastQuery = true;
        return $list;
    }

    /**
     * Get an array containing all the table names for this DB
     *
     * @param $table
     * @return array
     * @throws \Tk\Db\Exception
     */
    public function getTableInfo($table)
    {
        self::$logLastQuery = false;
        $list = array();
        $result = null;
        if ($this->getDriver() == 'mysql') {
            $sql = sprintf('DESCRIBE %s ', $this->quoteParameter($table));
            $result = $this->query($sql);
            if ($result) {
                $result->setFetchMode(\PDO::FETCH_ASSOC);
                foreach ($result as $row) {
                    $list[$row['Field']] = $row;
                }
            }
        } else if ($this->getDriver() == 'pgsql') { // Try to emulate the mysql DESCRIBE as close as possible
            $sql = sprintf('select * FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name =  %s', $this->quote($table));
            $result = $this->query($sql);
            $result->setFetchMode(\PDO::FETCH_ASSOC);
            foreach ($result as $row) {
                $list[$row['column_name']] = array(
                    'Field' => $row['column_name'],
                    'Type' => $row['data_type'],
                    'Null' => $row['is_nullable'],
                    'Key' => '',
                    'Default' => $row['column_default'],
                    'Extra' => ''
                );
                if (preg_match('/^nextval\(/', $row['column_default'])) {
                    $list[$row['column_name']]['Key'] = 'PRI';
                    $list[$row['column_name']]['Extra'] = 'auto_increment';
                }
            }
            $list = array_reverse($list);
        }
        self::$logLastQuery = true;
        return $list;
    }

    /**
     * drop a specific table
     *
     * @param $tableName
     * @return bool
     * @throws \Tk\Db\Exception
     */
    public function dropTable($tableName)
    {
        if (!$this->hasTable($tableName)) return false;
        $sql = '';
        if ($this->getDriver() == 'mysql') {
            $sql .= sprintf('SET FOREIGN_KEY_CHECKS = 0;SET UNIQUE_CHECKS = 0;');
        }
        $sql .= sprintf('DROP TABLE IF EXISTS %s CASCADE;', $this->quoteParameter($tableName));
        if ($this->getDriver() == 'mysql') {
            $sql .= sprintf('SET FOREIGN_KEY_CHECKS = 1;SET UNIQUE_CHECKS = 1;');
        }
        $this->exec($sql);
        return true;
    }

    /**
     * Remove all tables from a DB
     * You must send true as a parameter to ensure it executes
     *
     * @param bool $confirm
     * @param array $exclude
     * @return bool
     * @throws \Tk\Db\Exception
     * @todo Check this is compatible with MySQL???? Also may want to also drop procedures, view, etc. ????
     */
    public function dropAllTables($confirm = false, $exclude = array())
    {
        if (!$confirm) return false;
        $sql = '';
        if ($this->getDriver() == 'mysql') {
            $sql .= sprintf('SET FOREIGN_KEY_CHECKS = 0;SET UNIQUE_CHECKS = 0;');
        }
        foreach ($this->getTableList() as $i => $v) {
            if (in_array($v, $exclude)) continue;
            $sql .= sprintf('DROP TABLE IF EXISTS %s CASCADE;', $this->quoteParameter($v));
        }
        if ($this->getDriver() == 'mysql') {
            $sql .= sprintf('SET FOREIGN_KEY_CHECKS = 1;SET UNIQUE_CHECKS = 1;');
        }
        $this->exec($sql);
        return true;
    }

    /**
     * Get the insert id of the last added record.
     * Taken From: http://dev.mysql.com/doc/refman/5.0/en/innodb-auto-increment-handling.html
     *
     * @param string $table
     * @param string $pKey
     * @return int The next assigned integer to the primary key
     * @throws \Tk\Db\Exception
     */
    public function getNextInsertId($table, $pKey = 'id')
    {
        self::$logLastQuery = false;
        if ($this->getDriver() == 'mysql') {
            $table = $this->quote($table);
            $sql = sprintf('SHOW TABLE STATUS LIKE %s ', $table);
            $result = $this->query($sql);
            $result->setFetchMode(\PDO::FETCH_ASSOC);
            $row = $result->fetch();
            if ($row && isset($row['Auto_increment'])) {
                return (int)$row['Auto_increment'];
            }
            $sql = sprintf('SELECT MAX(`%s`) AS `lastId` FROM `%s` ', $pKey, $table);
            $result = $this->query($sql);
            $result->setFetchMode(\PDO::FETCH_ASSOC);
            $row = $result->fetch();
            return ((int)$row['lastId']) + 1;
        } if ($this->getDriver() == 'pgsql') {
            $sql = sprintf('SELECT * FROM %s_%s_seq', $table, $pKey);
            $result = $this->prepare($sql);
            $result->execute();
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            return ((int)$row['last_value']) + 1;
        }

        // Not as accurate as I would like and should not be relied upon.
        $sql = sprintf('SELECT %s FROM %s ORDER BY %s DESC LIMIT 1;', self::quoteParameter($pKey), self::quoteParameter($table), self::quoteParameter($pKey));
        $result = $this->query($sql);
        $result->setFetchMode(\PDO::FETCH_ASSOC);
        $row = $result->fetch();
        self::$logLastQuery = true;
        return $row[$pKey]+1;
    }

    /**
     * Encode string to avoid sql injections.
     *
     * @param string $str
     * @return string
     */
    public function escapeString($str)
    {
        if ($str) {
            return substr($this->quote($str), 1, -1);
        }
        return $str;
    }

    /**
     * @param $array
     * @return mixed
     */
    public function quoteParameterArray($array)
    {
        foreach($array as $k => $v) {
            $array[$k] = $this->quoteParameter($v);
        }
        return $array;
    }

    /**
     * Quote a parameter based on the quote system
     * if the param exists in the reserved words list
     *
     * @param string $param
     * @return string
     */
    public function quoteParameter($param)
    {
        return $this->parameterQuote . trim($param, $this->parameterQuote) . $this->parameterQuote;
    }

}


