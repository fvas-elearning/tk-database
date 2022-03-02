<?php
namespace Tk\DataMap;

use Tk\Db\ModelInterface;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2016 Michael Mifsud
 */
class DataMap
{
    static $EXCLUDED_PROPERTIES = array('del');

    /**
     * @var Map[]|array
     */
    private $propertyMaps = array();

    /**
     * Same as propertyMaps but the ky is the column name for fast searching
     * @var Map[]|array
     */
    private $columnMaps = array();

    /**
     * If this is true objects without the defined parameters (vars)
     *    will be created dynamically
     *
     * @var bool
     */
    protected $enableDynamicParameters = true;


    /**
     * Using the DataMap load an object with the values from an array
     * If the property does not exist as a map the value is added to
     * the object as a dynamic property if enableDynamicParameters is true.
     *
     * @link http://krisjordan.com/dynamic-properties-in-php-with-stdclass
     * @param array $row
     * @param null|ModelInterface $object
     * @param string $ignoreTag
     * @return \stdClass|ModelInterface|object
     */
    public function loadObject($row, $object = null, $ignoreTag = '')
    {
        if ($object === null) $object = new \stdClass();
        foreach ($row as $k => $v) {
            $map = $this->getPropertyMapByColumnName($k);
            if ($map) {
                if ($ignoreTag && $map->getTag() == $ignoreTag) continue;
                $map->loadObject($row, $object);
            } else {        // ONLY ADD DYNAMIC FIELDS IF THE OBJECT DOES NOT HAVE THIS PROPERTY ALREADY!!
                try {
                    if ($this->isEnableDynamicParameters()) {
                        $reflect = new \ReflectionClass($object);
                        if (!$reflect->hasProperty($k) && !in_array($k, self::$EXCLUDED_PROPERTIES)) {
                            $object->$k = $v;
                        }
                    }
                } catch (\ReflectionException $e) { }
            }
        }
        return $object;
    }

    /**
     * Using the DataMap load an array with the values from an object
     *
     * @param ModelInterface|\stdClass $obj
     * @param array $array
     * @param string $ignoreTag
     * @return array
     */
    public function loadArray($obj, $array = array(), $ignoreTag = '')
    {
        /* @var $map Map */
        foreach ($this->getPropertyMaps() as $map) {
            if ($ignoreTag && $map->getTag() == $ignoreTag) continue;
            $array = $map->loadArray($obj, $array);
        }
        return $array;
    }

    /**
     * Gets the list of property mappers.
     *
     * @param string $tag
     * @return array
     */
    public function getPropertyMaps($tag = null)
    {
        if ($tag) {
            $list = array();
            foreach ($this->propertyMaps as $map) {
                if ($map->getTag() == $tag)
                    $list[] = $map;
            }
            return $list;
        }
        return $this->propertyMaps;
    }

    /**
     * Gets a property map by its property name
     *
     * @param string $name
     * @return Map|null
     */
    public function getPropertyMap($name)
    {
        if (isset($this->propertyMaps[$name])) {
            return $this->propertyMaps[$name];
        }
    }

    /**
     * Gets a property map by its column name
     *
     * @param string $columnName
     * @return Map|null
     */
    public function getPropertyMapByColumnName($columnName)
    {
        if (isset($this->columnMaps[$columnName])) {
            return $this->columnMaps[$columnName];
        }
    }

    /**
     * Add a property to this map
     *
     * @param Map $propertyMap
     * @param string $tag
     * @return Map|null
     */
    public function addPropertyMap($propertyMap, $tag = null)
    {
        if ($tag) $propertyMap->setTag($tag);
        $this->propertyMaps[$propertyMap->getPropertyName()] = $propertyMap;
        $this->columnMaps[$propertyMap->getColumnName()] = $propertyMap;
        return $propertyMap;
    }

    /**
     * @return bool
     */
    public function isEnableDynamicParameters(): bool
    {
        return $this->enableDynamicParameters;
    }

    /**
     * @param bool $enableDynamicParameters
     * @return DataMap
     */
    public function setEnableDynamicParameters(bool $enableDynamicParameters): DataMap
    {
        $this->enableDynamicParameters = $enableDynamicParameters;
        return $this;
    }

}