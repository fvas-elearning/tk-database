<?php
namespace Tk\Db;



/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
class Tool implements \Tk\InstanceKey
{

    const PARAM_GROUP_BY = 'groupBy';
    const PARAM_HAVING = 'having';
    const PARAM_ORDER_BY = 'orderBy';
    const PARAM_LIMIT = 'limit';
    const PARAM_OFFSET = 'offset';
    const PARAM_DISTINCT = 'distinct';
    const PARAM_FOUND_ROWS = 'foundRows';
    
    
    /**
     * Limit the number of records retrieved.
     * If > 0 then mapper should query for the total number of records
     *
     * @var int
     */
    protected $limit = 0;

    /**
     * The record to start retrieval from
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * @var string
     */
    protected $orderBy = 'id DESC';

    /**
     * @var string
     */
    protected $groupBy = '';

    /**
     * @var string
     */
    protected $having = '';

    /**
     * @var bool
     */
    protected $distinct = true;

    /**
     * Instance base id
     * @var string
     */
    protected $instanceId = '';

    /**
     * The total number of rows found without LIMIT clause
     * @var int
     */
    protected $foundRows = 0;


    /**
     * @param string $orderBy
     * @param int $limit
     * @param int $offset
     * @param string $groupBy
     * @param string $having
     */
    public function __construct($orderBy = '', $limit = 0, $offset = 0, $groupBy = '', $having = '')
    {
        $this->setOrderBy($orderBy);
        $this->setLimit($limit);
        $this->setOffset($offset);
        $this->setGroupBy($groupBy);
        $this->setHaving($having);
    }

    /**
     * @param string $orderBy
     * @param int $limit
     * @param int $offset
     * @param string $groupBy
     * @param string $having
     * @return Tool
     */
    static function create($orderBy = '', $limit = 0, $offset = 0, $groupBy = '', $having = '')
    {
        return new self($orderBy, $limit, $offset, $groupBy, $having);
    }

    /**
     * Good to use when creating from a request or session array
     *
     * @param $array
     * @param string $defaultOrderBy
     * @param int $defaultLimit
     * @param string $instanceId
     * @return Tool
     */
    static function createFromArray($array, $defaultOrderBy = '', $defaultLimit = 0, $instanceId= '')
    {
        $obj = new self($defaultOrderBy, $defaultLimit);
        $obj->setInstanceId($instanceId);

        if (isset($array[$obj->makeInstanceKey(self::PARAM_OFFSET)])) {
            $obj->setOffset($array[$obj->makeInstanceKey(self::PARAM_OFFSET)]);
        }
        if (isset($array[$obj->makeInstanceKey(self::PARAM_ORDER_BY)])) {
            $obj->setOrderBy($array[$obj->makeInstanceKey(self::PARAM_ORDER_BY)]);
        }
        if (isset($array[$obj->makeInstanceKey(self::PARAM_LIMIT)])) {
            $obj->setLimit($array[$obj->makeInstanceKey(self::PARAM_LIMIT)]);
            //$obj->setOffset(0);
        }
        if (isset($array[$obj->makeInstanceKey(self::PARAM_GROUP_BY)])) {
            $obj->setGroupBy($array[$obj->makeInstanceKey(self::PARAM_GROUP_BY)]);
            //$obj->setOffset(0);
        }
        if (isset($array[$obj->makeInstanceKey(self::PARAM_HAVING)])) {
            $obj->setHaving($array[$obj->makeInstanceKey(self::PARAM_HAVING)]);
            //$obj->setOffset(0);
        }
        if (isset($array[$obj->makeInstanceKey(self::PARAM_DISTINCT)])) {
            $obj->setDistinct($array[$obj->makeInstanceKey(self::PARAM_DISTINCT)]);
        }
        return $obj;
    }

    /**
     * Use this to reload the tool from an array
     *
     * Use when creating from a session then load from the request to
     * create an updated tool.
     *
     * @param array $array
     * @return boolean Returns true if the object has been changed
     */
    public function updateFromArray($array)
    {
        $updated = false;
        if (isset($array[$this->makeInstanceKey(self::PARAM_ORDER_BY)])) {
            if ($array[$this->makeInstanceKey(self::PARAM_ORDER_BY)] != $this->getOrderBy()) {
                $this->setOrderBy($array[$this->makeInstanceKey(self::PARAM_ORDER_BY)]);
            }
            $updated = true;
        }
        if (isset($array[$this->makeInstanceKey(self::PARAM_LIMIT)])) {
            if ($array[$this->makeInstanceKey(self::PARAM_LIMIT)] != $this->getLimit()) {
                $this->setLimit($array[$this->makeInstanceKey(self::PARAM_LIMIT)]);
                $this->setOffset(0);
            }
            $updated = true;
        }
        if (isset($array[$this->makeInstanceKey(self::PARAM_OFFSET)])) {
            if ($array[$this->makeInstanceKey(self::PARAM_OFFSET)] != $this->getOffset()) {
                $this->setOffset($array[$this->makeInstanceKey(self::PARAM_OFFSET)]);
            }
            $updated = true;
        }
        if (isset($array[$this->makeInstanceKey(self::PARAM_GROUP_BY)])) {
            if ($array[$this->makeInstanceKey(self::PARAM_GROUP_BY)] != $this->getGroupBy()) {
                $this->setGroupBy($array[$this->makeInstanceKey(self::PARAM_GROUP_BY)]);
            }
            $updated = true;
        }
        if (isset($array[$this->makeInstanceKey(self::PARAM_HAVING)])) {
            if ($array[$this->makeInstanceKey(self::PARAM_HAVING)] != $this->getHaving()) {
                $this->setHaving($array[$this->makeInstanceKey(self::PARAM_HAVING)]);
            }
            $updated = true;
        }
        if (isset($array[$this->makeInstanceKey(self::PARAM_DISTINCT)])) {
            if ($array[$this->makeInstanceKey(self::PARAM_DISTINCT)] != $this->isDistinct()) {
                $this->setDistinct($array[$this->makeInstanceKey(self::PARAM_DISTINCT)]);
            }
            $updated = true;
        }

        return $updated;
    }


    /**
     * Get the current page number based on the limit and offset
     *
     * @return int
     */
    public function getPageNo()
    {
        return ceil($this->offset / $this->limit) + 1;
    }

    /**
     * @return int
     */
    public function getFoundRows()
    {
        return $this->foundRows;
    }

    /**
     * @param int $foundRows
     * @return Tool
     */
    public function setFoundRows($foundRows)
    {
        $this->foundRows = $foundRows;
        return $this;
    }

    /**
     * Set the order By value
     *
     * @param string $str
     * @return $this
     */
    public function setOrderBy($str)
    {
        if (strstr(strtolower($str), 'field') === false) {
            // TODO: HUMM!!!! Why are we doing this again????
            //$str = str_replace("'", "''", $str);
        }
        $this->orderBy = $str;
        return $this;
    }

    /**
     * Get the order by string for the DB queries
     *
     * @return string
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }

    /**
     * Get the order by property if available and can be found
     *
     * @return string
     */
    public function getOrderProperty()
    {
        $order = $this->getOrderBy();
        if ($order && !preg_match('/^(ASC|DESC|FIELD\(|IFNULL\(|RAND\(|IF\(|NULL|CASE)/', $order)) {
            if (preg_match('/^([a-z0-9]+\.)?([a-z0-9_\-]+)/i', $order, $regs)) {
                $order = trim($regs[2]);
            }
        }
        return $order;
    }

    /**
     * Set the limit value
     *
     * @param int $i
     * @return $this
     */
    public function setLimit($i)
    {
        if ($i <= 0) $i = 0;
        $this->limit = (int)$i;
        return $this;
    }

    /**
     * Get the page limit
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Set the offset value
     *
     * @param int $i
     * @return $this
     */
    public function setOffset($i)
    {
        if ($i <= 0) $i = 0;
        $this->offset = (int)$i;
        return $this;
    }

    /**
     * Get the record offset
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @return string
     */
    public function getGroupBy()
    {
        return $this->groupBy;
    }

    /**
     * @param string $groupBy
     * @return $this
     */
    public function setGroupBy($groupBy)
    {
        $this->groupBy = $groupBy;
        return $this;
    }

    /**
     * @return string
     */
    public function getHaving()
    {
        return $this->having;
    }

    /**
     * @param string $having
     * @return $this
     */
    public function setHaving($having)
    {
        $this->having = $having;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isDistinct()
    {
        return $this->distinct;
    }

    /**
     * @param boolean $b
     * @return $this
     */
    public function setDistinct($b)
    {
        $this->distinct = $b;
        return $this;
    }

    /**
     * Return an array with the parameters
     * Useful to save the params to the session or request
     *
     * @return array
     */
    public function toArray()
    {
        $arr = array();
        //if ($this->getOrderBy())  // Not needed as '' is a valid order by value
        $arr[$this->makeInstanceKey(self::PARAM_ORDER_BY)] = $this->getOrderBy();
        //if ($this->getLimit())
            $arr[$this->makeInstanceKey(self::PARAM_LIMIT)] = $this->getLimit();
        //if ($this->getOffset())
            $arr[$this->makeInstanceKey(self::PARAM_OFFSET)] = $this->getOffset();
        if ($this->getGroupBy())
            $arr[$this->makeInstanceKey(self::PARAM_GROUP_BY)] = $this->getGroupBy();
        if ($this->getHaving())
            $arr[$this->makeInstanceKey(self::PARAM_HAVING)] = $this->getHaving();

        //$arr[$this->makeInstanceKey(self::PARAM_DISTINCT)] = $this->isDistinct();
        return $arr;
    }


    /**
     * NOTE: If your object uses the Model uses \Tk\Db\Mapper it is best to use that ...Mapper::getToolSql($tool)
     * Return a string for the SQL query
     *
     * ORDER BY `cell`
     * LIMIT 10 OFFSET 30
     *
     * @param string $tblAlias
     * @param Pdo $db
     * @return string
     *
     *
     * TODO: We have an issue if we want to get the SQL and there is no mapper, maybe we should retain the tool toSql() function???
     */
    public function toSql($tblAlias = '', $db = null)
    {
        //\Tk\Log::warning('Using a deprecated function \Tk\Db\Tool::toSql()');

        // GROUP BY
        $groupBy = '';
        if ($this->getGroupBy()) {
            $groupBy = 'GROUP BY ' . str_replace(array(';', '-- ', '/*'), ' ', $this->getGroupBy());
        }

        // HAVING
        $having = '';
        if ($this->getHaving()) {
            $having = 'HAVING ' . str_replace(array(';', '-- ', '/*'), ' ', $this->getHaving());
        }

        // ORDER BY
        $orderBy = '';
        if ($this->getOrderBy()) {
            $orFields = trim(str_replace(array(';', '-- ', '/*'), ' ', $this->getOrderBy()));
            if ($tblAlias && $db) {
                if (strpos($tblAlias, '.') === false) {
                    $tblAlias = $tblAlias . '.';
                }
                if (!preg_match('/^(ASC|DESC|FIELD\(|\'|RAND|CONCAT|SUBSTRING\(|IF\(|NULL|CASE)/i', $orFields)) {
                    $arr = explode(',', $orFields);
                    foreach ($arr as $i => $str) {
                        $str = trim($str);
                        if (preg_match('/^(ASC|DESC|FIELD\(|\'|RAND|CONCAT|SUBSTRING\(|IF\(|NULL|CASE)/i', $str)) continue;
                        //if (!preg_match('/^([a-z]+\.)?`/i', $str)) continue;
                        //if (!preg_match('/^([a-zA-Z]+\.)/', $str) && is_string($str)) {
                        if (strpos($str, '.') === false) {
                            $a = explode(' ', $str);
                            $str = $tblAlias . $db->quoteParameter($a[0]);
                            if (isset($a[1])) {
                                $str = $str . ' ' . $a[1];
                            }
                        }
                        $arr[$i] = $str;
                    }
                    $orFields = implode(', ', $arr);
                }
            }
            $orderBy = 'ORDER BY ' . $orFields;
        }

        // LIMIT
        $limitStr = '';
        if ($this->getLimit() > 0) {
            $limitStr = 'LIMIT ' . (int)$this->getLimit();
            if ($this->getOffset()) {
                $limitStr .= ' OFFSET ' . (int)$this->getOffset();
            }
        }
        $sql = sprintf ('%s %s %s %s', $groupBy, $having, $orderBy, $limitStr);
        return $sql;
    }




    /**
     * Create request keys with prepended string
     *
     * returns: `{instanceId}_{$key}`
     *
     * @param $key
     * @return string
     */
    public function makeInstanceKey($key)
    {
        if ($this->instanceId)
            return $this->instanceId . '-' . $key;
        return $key;
    }

    /**
     * @param $str
     */
    public function setInstanceId($str)
    {
        $this->instanceId = $str;
    }
    
    
    
    
}