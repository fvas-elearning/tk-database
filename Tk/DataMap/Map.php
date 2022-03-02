<?php
namespace Tk\DataMap;

use Tk\ConfigTrait;
use Tk\Db\ModelInterface;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2016 Michael Mifsud
 */
abstract class Map
{
    use ConfigTrait;

    /**
     * The storage property name, (IE: db column name)
     * @var string
     */
    protected $columnName = '';

    /**
     * The object property name
     * @var string
     */
    protected $propertyName = '';

    /**
     * @todo Refactor this tag idea for primary keys in DB maps
     * @var string
     */
    protected $tag = '';



    /**
     * @param string $propertyName The object property to map the column to.
     * @param string $columnName (optional)The Db column name to map this property to. uses $propertyName if null
     */
    public function __construct($propertyName, $columnName = '')
    {
        $this->propertyName = $propertyName;
        if (!$columnName) {
            $columnName = $propertyName;
        }
        $this->columnName = $columnName;
    }

    /**
     * Map an array column value to an object property value
     *
     * @param array $row
     * @param string $columnName
     * @return mixed|null
     */
    public function toPropertyValue($row, $columnName)
    {
        if (isset($row[$columnName])) {
            return $row[$columnName];
        }
        return null;
    }

    /**
     * Map an object property value to an array column value
     * Returns null if property not found or value is null
     *
     * @param mixed $object
     * @param string $propertyName
     * @return string|null
     */
    public function toColumnValue($object, $propertyName)
    {
        $v = null;
        if ($this->objectPropertyExists($object, $propertyName)) {
            $v = $this->getObjectPropertyValue($object, $propertyName);
        }
        return $v;
    }

    /**
     * Default function to load an object property with a value
     *
     * @param $row
     * @param ModelInterface|\stdClass $object
     */
    public function loadObject($row, $object)
    {
        $name = $this->getPropertyName();
        $value = $this->toPropertyValue($row, $this->getColumnName());

        // TODO: This may no longer be needed???
//        if ($object instanceof \stdClass) {
//            \Tk\Log::warning('This code is used, remove comments to delete...');
//            $object->$name = $value;
//            return;
//        }

        \Tk\ObjectUtil::setObjectPropertyValue($object, $name, $value);
    }

    /**
     * Load an array with a map column
     *
     * @param ModelInterface|\stdClass $object
     * @param array $array
     * @return array
     */
    public function loadArray($object, $array)
    {
        $name = $this->getColumnName();
        $value = $this->toColumnValue($object, $this->getPropertyName());
        $array[$name] = $value;
        return $array;
    }

    /**
     * The object's instance property name
     *
     * @return string
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * return true if the column exists in the array
     *
     * @param ModelInterface|\stdClass $object
     * @return bool
     */
    public function hasProperty($object)
    {
        return $this->objectPropertyExists($object, $this->getPropertyName());
    }

    /**
     * This is the data source (DB) column name.
     *
     * @return string
     */
    public function getColumnName()
    {
        return $this->columnName;
    }

    /**
     * return true if the column exists in the array
     *
     * @param array $array
     * @return bool
     */
    public function hasColumn($array)
    {
        if (is_array($array))
            return array_key_exists($this->getColumnName(), $array);
        return false;
    }

    /**
     * allows for finding private properties
     *
     * NOTE: Accessing private properties is possible, but care must be taken
     * if that private property was defined lower into the inheritance chain.
     * For example, if class A extends class B, and class B defines a private
     * property called 'foo', getProperty will throw a ReflectionException.
     *
     * Instead, you can loop over getParentClass until it returns false to
     * look for the private property, at which point you can access and/or
     * modify its value as needed. (modify this method if needed)
     *
     * @param ModelInterface|\stdClass $object
     * @param string $name the property name
     * @return bool
     */
    protected function objectPropertyExists($object, $name)
    {
        $v = \Tk\ObjectUtil::objectPropertyExists($object, $name);
        return $v;
    }

    /**
     * Allows for getting of private properties
     *
     * NOTE: For private properties of subclasses see the note above.,.
     *
     * @param ModelInterface|\stdClass $object
     * @param string $name The property name
     * @return mixed|null
     */
    protected function getObjectPropertyValue($object, $name)
    {
        $v = \Tk\ObjectUtil::getObjectPropertyValue($object, $name);
        return $v;
    }



    // TODO: the below is to ambiguous, we need to refactor this

    /**
     * A tag to identify misc property settings. (IE: For db set 'key' to identify the primary key property(s))
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * A tag to identify misc property settings. (IE: For db set 'key' to identify the primary key property(s))
     * @param string $tag
     */
    public function setTag($tag)
    {
        $this->tag = $tag;
    }

}