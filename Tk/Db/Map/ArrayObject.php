<?php
namespace Tk\Db\Map;

use Tk\Db\PdoStatement;
use Tk\Db\Tool;

/**
 * This objected is essentially a wrapper around the PdoStatement object with added features
 * such as holding the Model Mapper, and Tool objects.
 *
 * It automatically maps an objects data if the Model has the magic methods available
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 * @todo: Move this to the Tk::Db namespace so the FQN is Tk::Db::ArrayObject
 */
class ArrayObject implements \Iterator, \Countable
{

    /**
     * @var Mapper
     */
    protected $mapper = null;

    /**
     * The raw database rows as associative arrays.
     * @var PdoStatement
     */
    protected $statement = null;

    /**
     * The raw database rows as associative arrays.
     * @var array
     */
    protected $rows = null;

    /**
     * @var int
     */
    protected $idx = 0;

    /**
     * The total number of rows found without LIMIT clause
     * @var int
     * @deprecated This has been mooved to \Tk\Db\Tool
     */
    protected $foundRows = 0;

    /**
     * This may or may not exist depending on the source of the array data
     * @var Tool
     */
    protected $tool = null;



    /**
     * Create a DB array list object
     *
     * @param array $rows
     */
    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    /**
     * Create an array object that uses the DB mappers to load the object
     *
     * @param Mapper $mapper
     * @param PdoStatement $statement
     * @param Tool $tool
     * @return ArrayObject
     * @throws \Tk\Db\Exception
     */
    static function createFromMapper(Mapper $mapper, PdoStatement $statement, $tool = null)
    {
        // TODO: One day PDO may be able to do this serially, this is a big memory hog...
        //       Currently we cannot subclass the PDOStatement::fetch...() methods correctly [php: 5.6.27]
        // NOTE: For large datasets that could fill the memory, this object should not be used
        //       instead get statement and manually iterate the data.
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $obj = new self($rows);
        $obj->mapper = $mapper;
        $obj->statement = $statement;
        if (!$tool) {
            $tool = new Tool();
        }
        $obj->tool = $tool;
        $tool->setFoundRows($mapper->getDb()->countFoundRows($statement->queryString));
        $obj->foundRows = $tool->getFoundRows();
        return $obj;
    }

    /**
     * Create an array object from an SQL statement when no mappers and objects area used
     *
     * @param \Tk\Db\PdoStatement $statement
     * @param null|\Tk\Db\Tool $tool
     * @param int $foundRows
     * @return ArrayObject
     * @throws \Tk\Db\Exception
     */
    static function create(PdoStatement $statement, $tool = null, $foundRows = null)
    {
        // TODO: One day PDO may be able to do this serially, this is a big memory hog...
        //       Currently we cannot subclass the PDOStatement::fetch...() methods correctly [php: 5.6.27]
        // NOTE: For large datasets that could fill the memory, this object should not be used
        //       instead get statement and manually iterate the data.
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $obj = new self($rows);
        if ($foundRows === null) {
            if ($tool && $tool->getFoundRows()) {
                $foundRows = $tool->getFoundRows();
            } else {
                $foundRows = count($rows);
                if (method_exists($statement, 'getPdo') && $statement->getPdo())
                    $foundRows = $statement->getPdo()->countFoundRows($statement->queryString);
            }
        }
        /*
        $obj->foundRows = count($rows);
        if (method_exists($statement, 'getPdo') && $statement->getPdo())
            $obj->foundRows = $statement->getPdo()->countFoundRows($statement->queryString);
        if ($foundRows)
            $obj->foundRows = $foundRows;
        */
        $obj->statement = $statement;
        if (!$tool) {
            $tool = new Tool();
        }
        $obj->tool = $tool;
        $tool->setFoundRows($foundRows);
        $obj->foundRows = $tool->getFoundRows();
        return $obj;
    }

    /**
     * Destructor
     *
     */
    public function __destruct()
    {
        $this->statement = null;
        $this->tool = null;
    }

    /**
     * Return the tool object associated to this result set.
     * May not exist.
     *
     * @return Tool
     */
    public function getTool()
    {
        return $this->tool;
    }

    /**
     * Return the tool object associated to this result set.
     * May not exist.
     *
     * @return Mapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * 
     * @return PdoStatement
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * Get the result rows as a standard array.
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @param int $i
     * @return mixed
     */
    public function get($i)
    {
        if (isset($this->rows[$i])) {
            if ($this->mapper) {
                return $this->mapper->map($this->rows[$i]);
            }
            return (object)$this->rows[$i];
        }
        return null;
    }

    /**
     * Return the total number of rows found.
     * When using SQL it would be the query with no limit...
     *
     * @return int
     */
    public function countAll()
    {
        if ($this->getTool()) {
            return $this->getTool()->getFoundRows();
        }
        return $this->foundRows;
    }

    /**
     * Return the total number of rows found.
     * When using SQL it would be the query with no limit...
     *
     * @return int
     * @deprecated Use self::countAll()
     */
    public function getFoundRows()
    {
        return $this->countAll();
    }

    //   Countable Interface

    /**
     * Count the number of records returned from the SQL query
     *
     * @return int
     */
    public function count()
    {
        return count($this->rows);
    }


    //   Iterator Interface

    /**
     * rewind
     *
     * @return $this
     */
    public function rewind()
    {
        $this->idx = 0;
        return $this;
    }

    /**
     * Return the element at the current index
     *
     * @return \Tk\Db\Map\Model
     */
    public function current()
    {
        return $this->get($this->idx);
    }

    /**
     * Increment the counter
     *
     * @return \Tk\Db\Map\Model
     */
    public function next()
    {
        $this->idx++;
        return $this->current();
    }

    /**
     * get the key value
     *
     * @return int
     */
    public function key()
    {
        return $this->idx;
    }

    /**
     * Valid
     *
     * @return bool
     */
    public function valid()
    {
        if ($this->current()) {
            return true;
        }
        return false;
    }

    /**
     * If the keyField and-or value field are set then this will
     * return the array with a key and the required value.
     *
     * @param null $valueField
     * @param null $keyField
     * @return array
     */
    public function toArray($valueField = null, $keyField = null)
    {
        $arr = array();
        foreach($this as $k => $obj) {
            $v = $obj;
            if ($valueField && array_key_exists($valueField, get_object_vars($obj))) {
                $v = $obj->$valueField;
            }
            if ($keyField && array_key_exists($keyField, get_object_vars($obj))) {
                $k = $obj->$keyField;
            }
            $arr[$k] = $v;
        }
        return $arr;
    }
}
