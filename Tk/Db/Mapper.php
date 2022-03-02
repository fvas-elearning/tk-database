<?php
namespace Tk\Db;


use Tk\ConfigTrait;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2016 Michael Mifsud
 */
abstract class Mapper extends \Tk\Db\Map\Mapper
{
    use ConfigTrait;

    /**
     * @var \Tk\DataMap\DataMap
     */
    protected $dbMap = null;

    /**
     * @var \Tk\DataMap\DataMap
     */
    protected $formMap = null;


    /**
     * @param null|Pdo $db
     * @throws \Exception
     */
    public function __construct($db)
    {
        parent::__construct($db);
        $dbMap = $this->getDbMap();

        if ($dbMap && count($dbMap->getPropertyMaps('key'))) {
            $map = current($dbMap->getPropertyMaps('key'));
            $this->setPrimaryKey($map->getColumnName());
            $this->setPrimaryKeyProperty($map->getPropertyName());
        }
        $this->getFormMap();
    }

    /**
     * Override to return a valid DataMap
     * @return \Tk\DataMap\DataMap|null
     */
    public function getDbMap()
    {
        return null;
    }

    /**
     * Override to return a valid DataMap
     * @return \Tk\DataMap\DataMap|null
     */
    public function getFormMap()
    {
        return null;
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
     * @return \stdClass|Map\Model
     * @throws \Exception
     * @since 2.0.0
     */
    public function map($row, $obj = null)
    {
        if (!$obj) {
            $class = $this->getModelClass();
            if (class_exists($class))
                $obj = new $class();
        }
        // TODO: Check this is ok here and in the Model obj
        if (isset($row['del']))
            $obj->del = (bool)$row['del'];

        if ($this->getDbMap())
            return $this->getDbMap()->loadObject($row, $obj);
        return (object)$row;
    }

    /**
     * Un-map an object to an array ready for DB insertion.
     * All fields and types must match the required DB types.
     *
     * Input: This requires a Tk.Model or stdClass object as input
     *
     * Output: array (
     *   'tblColumn' => 'columnValue'
     * )
     *
     * @param Map\Model|\stdClass $obj
     * @param array $array
     * @return array
     * @throws \Exception
     * @since 2.0.0
     */
    public function unmap($obj, $array = array())
    {
        return $this->getDbMap()->loadArray($obj, $array);
    }

    /**
     * Map the form fields data to the object
     *
     * @param array $row
     * @param mixed $obj
     * @param string $ignore
     * @return mixed
     * @throws \Exception
     */
    public function mapForm($row, $obj = null, $ignore = 'key')
    {
        if (!$obj) {
            $class = $this->getModelClass();
            $obj = new $class();
        }
        return $this->getFormMap()->loadObject($row, $obj, $ignore);
    }

    /**
     * Unmap the object to an array for the form fields
     *
     * @param mixed $obj
     * @param array $array
     * @return array
     * @throws \Exception
     */
    public function unmapForm($obj, $array = array())
    {
        if (!$this->getFormMap())
            throw new \Tk\Db\Exception(''.get_class($this).'::getFormMap() method not implemented! Please contact your developer.');
        return $this->getFormMap()->loadArray($obj, $array);
    }

    /**
     * Override this to modify the tool's orderBy in-case the Model property has been used instead of the
     * mapped DB column name
     *
     * @param string $from
     * @param string $where
     * @param null|\Tk\Db\Tool $tool
     * @param string $select
     * @return Map\ArrayObject|\Tk\Db\PdoStatement
     * @throws \Exception
     */
    public function selectFrom($from = '', $where = '', $tool = null, $select = '')
    {
//        if ($tool && $tool->getOrderProperty()) {   // Do nothing if a property cannot be found in the tool
//            $tool = $this->cleanTool($tool);
//        }
        return parent::selectFrom($from, $where, $tool, $select);
    }

    /**
     * @param \Tk\Db\Filter $filter
     * @param null|\Tk\Db\Tool $tool
     * @return Map\ArrayObject|\Tk\Db\PdoStatement
     * @throws \Exception
     */
    public function selectFromFilter($filter, $tool = null)
    {
        return $this->selectFrom($filter->getFrom(), $filter->getWhere(), $tool, $filter->getSelect());
    }

    /**
     * Return a string for the SQL query
     *
     * ORDER BY `cell`
     * LIMIT 10 OFFSET 30
     *
     * @param \Tk\Db\Tool $tool
     * @return string
     */
    public function getToolSql($tool)
    {
        // GROUP BY
        // TODO: Map any properties to columns

        // HAVING
        // TODO: map any properties to columns

        // ORDER BY
        // TODO: map any properties to columns
        if ($this->getDbMap() && $tool->getOrderProperty()) {
            $mapProperty = $this->getDbMap()->getPropertyMap($tool->getOrderProperty());
            // TODO: also check for whitespace or reserved chars as that can indicate it is not mappable
            if ($mapProperty && $tool->getOrderProperty() != $mapProperty->getColumnName()) {
                $tool = clone $tool; // Clone this so the orderBy properties are not changed in the original Tool object for table sort order links.
                $orderBy = $tool->getOrderBy();
                $orderBy = str_replace($tool->getOrderProperty(), $mapProperty->getColumnName(), $orderBy);
                $tool->setOrderBy($orderBy);
            }

        }
        $sql = parent::getToolSql($tool);

        return $sql;
    }

    /**
     * Override this to modify the tool's orderBy in-case the Model property has been used instead of the
     * mapped DB column name
     *
     * @param null|\Tk\Db\Tool $tool
     * @return null|\Tk\Db\Tool
     * @throws \Exception
     * @deprecated
     */
    public function cleanTool($tool)
    {
        if ($tool) {
            // TODO: I would prefer not to create a new instance here if possible
            // TODO:  It is here so we can map the orderBy properties to columns from the table sort order links.
            //$tool = clone $tool; // Clone this so the orderBy properties are not changed in the original tool object for table sort order links.

            $mapProperty = $this->getDbMap()->getPropertyMap($tool->getOrderProperty());
            if ($mapProperty) {
                $orderBy = $tool->getOrderBy();
                $orderBy = str_replace($tool->getOrderProperty(), $mapProperty->getColumnName(), $orderBy);
                $tool->setOrderBy($orderBy);
            }
        }
        return $tool;
    }

    /**
     * Create a sql query string from an array.
     * Handy for testing multiple values
     * EG:
     *   "a.type = 'Self Assessment' AND a.type != 'Testing' AND 'Thinking'"
     *
     * @param array|mixed $value
     * @param string $columnName
     * @param string $logic   Logical Operator
     * @param string $compare  Comparison Operator
     * @return string
     */
    public function makeMultiQuery($value, $columnName, $logic = 'OR', $compare = '=')
    {
        return self::makeMultipleQuery($value, $columnName, $logic, $compare);
    }


    /**
     * Create a sql query string from an array.
     * Handy for testing multiple values
     * EG:
     *   "a.type = 'Self Assessment' AND a.type != 'Testing' AND 'Thinking'"
     *
     * @param array|mixed $value
     * @param string $columnName
     * @param string $logic   Logical Operator
     * @param string $compare  Comparison Operator
     * @return string
     */
    public static function makeMultipleQuery($value, $columnName, $logic = 'OR', $compare = '=')
    {
        if (!is_array($value)) $value = array($value);
        $w = '';
        foreach ($value as $r) {
            if ($r === null || $r === '') continue;
            $w .= sprintf('%s %s %s %s ', $columnName, $compare, \Tk\Config::getInstance()->getDb()->quote($r), $logic);
        }
        if ($w)
            $w = rtrim($w, ' '.$logic.' ');
        return $w;
    }
}