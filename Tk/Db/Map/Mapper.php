<?php
namespace Tk\Db\Map;

use Tk\Db\Pdo;
use Tk\Db\Exception;
use Tk\Db\Tool;

/**
 * Some reserved column names and assumed meanings:
 *  - `id`       => An integer that is assumed to be the records primary key
 *                  foreign keys are assumed to be named `<foreign_table>_id`
 *  - `modified` => A timestamp that gets incremented on updates
 *  - `created`  => A timestamp not really reserved but assumed
 *  - `del`      => If it exists the records are marked `del` = 1 rather than deleted
 *
 * If your columns conflict, then you should modify the mapper or DB accordingly
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
abstract class Mapper implements Mappable
{
    /**
     * @var string
     */
    public static $DB_PREFIX = '';

    /**
     * Set this to false to allow records that have been deleted to be retrieved
     * @var bool
     */
    public static $HIDE_DELETED = true;

    /**
     * Set this to false to allow created and modified dates to be set from the model
     * @var bool
     */
    public static $AUTO_DATES = true;
    
    /**
     * @var Mapper[]
     */
    protected static $instance = array();

    /**
     * @var string
     */
    protected $table = '';

    /**
     * @var string
     */
    protected $modelClass = '';

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @var string
     */
    protected $primaryKeyProperty = 'id';

    /**
     * @var Pdo
     */
    protected $db = null;

    /**
     * @var string
     */
    protected $alias = 'a';

    /**
     * @var null
     */
    protected $tableInfo = null;

    /**
     * @var string
     */
    protected $markDeleted = '';


    /**
     * @param null|Pdo $db
     * @throws \Exception
     */
    public function __construct($db = null)
    {
        if (!$db)
            $db = Pdo::getInstance();
        $this->setDb($db);
        $this->setModelClass($this->getDefaultModelClass());
        $this->setTable($this->getDefaultTable());
    }

    /**
     * Get/Create an instance of a data mapper.
     *
     * @param Pdo $db
     * @return static
     */
    static function create($db = null)
    {
        $mapperClass = get_called_class(); // PHP >= v5.5 use: $mapperClass = static::class;
        if (version_compare(PHP_VERSION, '5.5.0', '>=')) {
            $mapperClass = static::class;
        }
        if (!isset(self::$instance[$mapperClass])) {
            self::$instance[$mapperClass] = new $mapperClass($db);
        }
        return self::$instance[$mapperClass];
    }

    /**
     * Map the data from a DB row to the required object
     *
     * Input: array (
     *   'tblColumn' => 'columnValue'
     * )
     *
     * Output: Should return an \stdClass or \Tk\Model object
     *
     * @param array $row
     * @param null|mixed $obj If null then \stdClass will be returned
     * @return \stdClass|\Tk\Db\Map\Model
     * @since 2.0.0
     */
    public function map($row, $obj = null)
    {
        return (object)$row;
    }

    /**
     * Un-map an object to an array ready for DB insertion.
     * All fields and types must match the required DB types.
     *
     * Input: This requires a \Tk\Db\Map\Model or \stdClass object as input
     *
     * Output: array (
     *   'tblColumn' => 'columnValue'
     * )
     *
     * @param \Tk\Db\Map\Model|\stdClass $obj
     * @param array $array
     * @return array
     * @since 2.0.0
     */
    public function unmap($obj, $array = array())
    {
        return (array)$obj;
    }


    /**
     * @param Model $obj
     * @return int Returns the new insert id
     * @throws Exception
     */
    public function insert($obj)
    {
        $bind = $this->unmap($obj);
        if (isset($bind[$this->getPrimaryKey()]))
            unset($bind[$this->getPrimaryKey()]);
        $keys = array_keys($bind);
        $cols = implode(', ', $this->getDb()->quoteParameterArray($keys));
        $values = implode(', :', array_keys($bind));
        foreach ($bind as $col => $value) {
            // TODO: Look into using the following so we no longer have to manage the created and modified fields:
            // TODO:   `modified` DATETIME ON UPDATE CURRENT_TIMESTAMP,
            // TODO:   `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
            if (self::$AUTO_DATES) {
                if ($col == 'modified' || $col == 'created') {
                    //$value = date('Y-m-d H:i:s.u');
                    $value = date('Y-m-d H:i:s');
                }
            }
            unset($bind[$col]);
            $bind[':' . $col] = $value;
        }
        $sql = 'INSERT INTO ' . $this->quoteTable($this->table) . ' (' . $cols . ')  VALUES (:' . $values . ')';
        $this->getDb()->prepare($sql)->execute($bind);

        $seq = '';
        if ($this->getDb()->getDriver() == 'pgsql') {   // Generate the seq key for Postgres only
            $seq = $this->getTable().'_'.$this->getPrimaryKey().'_seq';
        }
        $id = (int)$this->getDb()->lastInsertId($seq);
        return $id;
    }

    /**
     * @param Model $obj
     * @return int
     * @throws Exception
     */
    public function update($obj)
    {
        $pk = $this->getPrimaryKey();
        $bind = $this->unmap($obj);
        $set = array();
        foreach ($bind as $col => $value) {
            if (self::$AUTO_DATES) {
                if ($col == 'modified') {
                    $value = date('Y-m-d H:i:s');
                }
            }
            unset($bind[$col]);
            $bind[':' . $col] = $value;
            $set[] = $this->quoteParameter($col) . ' = :' . $col;
        }
        $where = $this->quoteParameter($pk) . ' = ' . $bind[':'.$pk];
        $sql = 'UPDATE ' . $this->quoteTable($this->table) . ' SET ' . implode(', ', $set) . (($where) ? ' WHERE ' . $where : ' ');
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($bind);

        return $stmt->rowCount();
    }

    /**
     * @param Model $obj
     * @return int
     * @throws Exception
     */
    public function delete($obj)
    {
        $pk = $this->getPrimaryKey();
        $pkp = $this->getPrimaryKeyProperty();
        $where = $this->quoteParameter($pk) . ' = ' . $obj->$pkp;
        if ($where) {
            $where = 'WHERE ' . $where;
        }
        //TODO: User prepared statements
        $sql = sprintf('DELETE FROM %s %s LIMIT 1', $this->quoteParameter($this->table), $where);
        if ($this->markDeleted) {
            $sql = sprintf('UPDATE %s SET %s = 1 %s LIMIT 1', $this->quoteTable($this->table), $this->quoteParameter($this->getMarkDeleted()), $where);
        }
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }


    /**
     * A Utility method that checks the id and does and insert
     * or an update  based on the objects current state
     *
     * @param Model $obj
     * @throws \Exception
     */
    public function save($obj)
    {
        $pkp = $this->getPrimaryKeyProperty();
        if (!property_exists($obj, $pkp)) {
            throw new \Tk\Exception('No valid primary key found in object: ' . get_class($obj));
        }
        if ($obj->$pkp) {
            $obj->update();
        } else {
            $obj->insert();
        }
    }

    /**
     * A select query using a prepared statement. Less control
     *
     * @param array $bind
     * @param Tool $tool
     * @param string $boolOperator
     * @return ArrayObject
     * @throws \Exception
     * @see http://www.sitepoint.com/integrating-the-data-mappers/
     * @deprecated TODO: See if we need this ?
     */
    public function selectPrepared($bind = array(), $tool = null, $boolOperator = 'AND')
    {
        if (!$tool instanceof Tool) {
            $tool = new Tool();
        }

        $alias = $this->getAlias();
        if ($alias) {
            $alias = $alias . '.';
        }

        if (self::$HIDE_DELETED && $this->getMarkDeleted() && !array_key_exists($this->getMarkDeleted(), $bind)) {
            $bind[$this->getMarkDeleted()] = '0';
        }

        $from = $this->getTable() . ' ' . $this->getAlias();
        $where = array();
        if ($bind) {
            foreach ($bind as $col => $value) {
                unset($bind[$col]);
                $bind[':' . $col] = $value;
                $where[] = $alias. $this->quoteParameter($col) . ' = :' . $col;
            }
        }
        $where = implode(' ' . $boolOperator . ' ', $where);

        // Build Query
        $foundRowsKey = '';
        if ($this->getDb()->getDriver() == 'mysql') {
            $foundRowsKey = 'SQL_CALC_FOUND_ROWS';
        }
        $sql = sprintf('SELECT %s %s * FROM %s %s ',
            $foundRowsKey,
            $tool->isDistinct() ? 'DISTINCT' : '',
            $from,
            ($bind) ? ' WHERE ' . $where : ' '
        );
        //$sql .= $tool->toSql();
        $sql .= $this->getToolSql($tool);

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($bind);

        $arr = ArrayObject::createFromMapper($this, $stmt, $tool);
        return $arr;
    }

    /**
     * Select a number of elements from a database
     *
     * @param string $from
     * @param string $where EG: "`column1` = 4 AND `column2`='string'"
     * @param Tool $tool
     * @param string $select
     * @return \Tk\Db\PdoStatement|ArrayObject
     * @throws \Exception
     */
    public function selectFrom($from = '', $where = '', $tool = null, $select = '')
    {
        if (!$tool instanceof Tool) {
            $tool = new Tool();
        }

        $alias = $this->getAlias();
        if ($alias) $alias = $alias . '.';

        if (!$from) {
            $from = sprintf('%s %s', $this->quoteTable($this->getTable()), $this->getAlias());
        }

        if (self::$HIDE_DELETED && $this->getMarkDeleted() && strstr($where, $this->quoteParameter($this->getMarkDeleted())) === false) {
            if ($where) {
                $where = sprintf('%s%s = 0 AND %s ', $alias, $this->quoteParameter($this->getMarkDeleted()), $where);
            } else {
                $where = sprintf('%s%s = 0 ', $alias, $this->quoteParameter($this->getMarkDeleted()));
            }
        }

        if ($where) {
            $where = 'WHERE ' . $where;
        }
        $distinct = '';
        if ($tool->isDistinct()) {
            $distinct = 'DISTINCT';
        }

        // OrderBy, GroupBy, Limit, etc
        $toolStr = '';
        if ($tool) {
            //$toolStr = $tool->toSql($alias, $this->getDb());
            $toolStr = $this->getToolSql($tool);
        }

        $foundRowsKey = '';
        if ($this->getDb()->getDriver() == 'mysql') {
            $foundRowsKey = 'SQL_CALC_FOUND_ROWS';
        }

        if (!$select) {
            $select = $alias.'*';
        }

        $sql = sprintf('SELECT %s %s %s FROM %s %s %s ', $foundRowsKey, $distinct, $select, $from, $where, $toolStr);
        $stmt = $this->getDb()->prepare($sql);
        
        $stmt->execute();

        $arr = ArrayObject::createFromMapper($this, $stmt, $tool);
        return $arr;
    }

    /**
     * Select a number of elements from a database
     *
     * @param string $where EG: "`column1`=4 AND `column2`='string'"
     * @param Tool $tool
     * @return ArrayObject
     * @throws \Exception
     */
    public function select($where = '', $tool = null)
    {
        return $this->selectFrom('', $where, $tool);
    }

    /**
     * Return a string for the SQL query
     *
     * ORDER BY `cell`
     * LIMIT 10 OFFSET 30
     *
     * TODO: We have an issue if we want to get the SQL and there is no mapper, maybe we should retain the tool toSql() function???
     *
     * @param \Tk\Db\Tool $tool
     * @return string
     */
    public function getToolSql($tool)
    {
        // GROUP BY
        // TODO: Map any properties to columns
        $groupBy = '';
        if ($tool->getGroupBy()) {
            $groupBy = 'GROUP BY ' . str_replace(array(';', '-- ', '/*'), ' ', $tool->getGroupBy());
        }

        // HAVING
        // TODO: map any properties to columns
        $having = '';
        if ($tool->getHaving()) {
            $having = 'HAVING ' . str_replace(array(';', '-- ', '/*'), ' ', $tool->getHaving());
        }

        // ORDER BY
        // TODO: map any properties to columns
        $orderBy = '';
        if ($tool->getOrderBy()) {
            $ordFieldsStr = trim(str_replace(array(';', '-- ', '/*'), ' ', $tool->getOrderBy()));
            $alias = $this->getAlias();
            if ($alias) {
                $alias = $alias . '.';
                if (!preg_match('/^(ASC|DESC|FIELD\(|\'|RAND|CONCAT|SUBSTRING\(|IF\(|NULL|CASE)/i', $ordFieldsStr)) {
                    $ordFields = explode(',', $ordFieldsStr);
                    foreach ($ordFields as $i => $str) {
                        $str = trim($str);
                        if (preg_match('/^(ASC|DESC|FIELD\(|\'|RAND|CONCAT|SUBSTRING\(|IF\(|NULL|CASE)/i', $str)) continue;
                        if (strpos($str, '.') === false) {
                            $a = explode(' ', $str);
                            $str = $alias . $this->quoteParameter($a[0]);
                            if (isset($a[1])) {
                                $str = $str . ' ' . $a[1];
                            }
                        }
                        $ordFields[$i] = $str;
                    }
                    $ordFieldsStr = implode(',', $ordFields);
                }
            }
            $orderBy = 'ORDER BY ' . $ordFieldsStr;
        }

        // LIMIT
        $limitStr = '';
        if ($tool->getLimit() > 0) {
            $limitStr = 'LIMIT ' . (int)$tool->getLimit();
            if ($tool->getOffset()) {
                $limitStr .= ' OFFSET ' . (int)$tool->getOffset();
            }
        }
        $sql = sprintf ('%s %s %s %s', $groupBy, $having, $orderBy, $limitStr);
        return $sql;
    }


    /**
     *
     * @param int $id
     * @return null|Model|\Tk\Db\ModelInterface
     * @throws \Exception
     */
    public function find($id)
    {

//        $b = self::$HIDE_DELETED;
//        self::$HIDE_DELETED = false;
        $where = sprintf('%s = %s', $this->quoteParameter($this->getPrimaryKey()), (int)$id);
        $list = $this->select($where, null);
        //self::$HIDE_DELETED = $b;

        return $list->current();
    }

    /**
     * Find all objects in DB
     *
     * @param Tool $tool
     * @return ArrayObject
     * @throws \Exception
     */
    public function findAll($tool = null)
    {
        return $this->select('', $tool);
    }

    /**
     * Generate the default model class from this mapper class
     * if a specific model class is required then use $this->setModelClass()
     *
     * @return string
     */
    protected function getDefaultModelClass()
    {
        $mapperClass = get_class($this);
        if (preg_match('/(.+)(Map|Mapper)$/', $mapperClass, $regs)) {
            return $regs[1];
        }
        return '';
    }

    /**
     * Generate the default table class.
     * If a specific table name is required then use $this->setTable()
     *
     * @return mixed|string
     */
    protected function getDefaultTable()
    {
        if ($this->modelClass) {
            $arr = explode('\\', $this->modelClass);
            $table = array_pop($arr);
            $table = $this->toDbProperty($table);
            return $table;
        }
        return '';
    }

    /**
     * If set to a column name then only mark the row deleted do not delete
     *
     * @param string $col Set to null string to remove this as an option
     * @return $this
     */
    public function setMarkDeleted($col = '')
    {
        $this->markDeleted = $col;
        return $this;
    }

    /**
     * Returns the name of the column to mark deleted. (update col to 1)
     * returns null if we are to physically delete the record
     *
     * @return string
     */
    public function getMarkDeleted()
    {
        return $this->markDeleted;
    }


    /**
     * Convert camelCase property names to underscore db property name
     *
     * EG: 'someProperty' is converted to 'some_property'
     *
     * @param string $property
     * @return string
     */
    public function toDbProperty($property)
    {
        return ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $property)), '_');
    }

    /**
     * @return string
     */
    public function getModelClass()
    {
        return $this->modelClass;
    }

    /**
     * @param string $modelClass
     * @return $this
     */
    public function setModelClass($modelClass)
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * Get the table alias used for multiple table queries.
     * The default alias is 'a'
     *
     *   EG: a.`id`
     *
     * @return string
     */
    public function getAlias()
    {
        return rtrim($this->alias, '.');
    }

    /**
     * Set the table alias
     *
     * @param string $alias
     * @return $this
     * @throws \Exception
     */
    public function setAlias($alias)
    {
        $alias = trim($alias, '.');
        if (!$alias || preg_match('/[a-z0-9_]+/i', $alias))
            $this->alias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param string $table
     * @param bool $addPrefix Set this to false to not add the table prefix.
     * @return $this
     */
    public function setTable($table, $addPrefix = true)
    {
        if ($addPrefix && self::$DB_PREFIX) {
            $table = self::$DB_PREFIX . $table;
        }

        $this->table = $table;
        try {
            if ($this->getDb()->hasTable($this->table)) {
                $this->tableInfo = $this->getDb()->getTableInfo($this->table);
            }
        } catch(\Exception $e) { $this->tableInfo = array(); }
        return $this;
    }

    /**
     * Get the table db prefix if one is set.
     *
     * @return string
     */
    public function getPrefix()
    {
        return self::$DB_PREFIX;
    }

    /**
     * If a colum name is supplied then that column info is returned
     *
     * @param null|string $column
     * @return null|array
     */
    public function getTableInfo($column = null)
    {
        if ($column) {
            return $this->tableInfo[$column];
        }
        return $this->tableInfo;
    }

    /**
     * @param string $column
     * @return bool
     */
    public function hasColumn($column)
    {
        return array_key_exists($column, $this->tableInfo);
    }

    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @param string $primaryKey
     * @return $this
     */
    public function setPrimaryKey($primaryKey)
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getPrimaryKeyProperty()
    {
        return $this->primaryKeyProperty;
    }

    /**
     * @param string $primaryKeyProperty
     * @return $this
     */
    public function setPrimaryKeyProperty($primaryKeyProperty)
    {
        $this->primaryKeyProperty = $primaryKeyProperty;
        return $this;
    }

    /**
     * @return Pdo
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * @param Pdo $db
     * @return $this
     */
    public function setDb($db)
    {
        $this->db = $db;
        return $this;
    }


    /**
     * Use this function to quote and escape a table name and add a prefix if it is set
     *
     * @param string $table
     * @param string|null $prefix (optional) Override default prefix
     * @return string
     */
    public function quoteTable($table, $prefix = null)
    {
        if ($prefix === null)
            $prefix = $this->getPrefix();
        return  $this->getDb()->quoteParameter($prefix . $table);
    }

    /**
     * Use this function to escape a table name and add a prefix if it is set
     *
     * @param string $str
     * @return string
     */
    public function quote($str)
    {
        return  $this->getDb()->quote($str);
    }

    /**
     * Quote a parameter based on the quote system
     * if the param exists in the reserved words list
     *
     * @param $str
     * @return string
     */
    public function quoteParameter($str)
    {
        return  $this->getDb()->quoteParameter($str);
    }

    /**
     * Encode string to avoid sql injections.
     *
     * @param string $str
     * @return string
     */
    public function escapeString($str)
    {
        return  $this->getDb()->escapeString($str);
    }



}