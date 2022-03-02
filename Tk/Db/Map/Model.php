<?php
namespace Tk\Db\Map;

use Tk\ConfigTrait;
use Tk\Db\Pdo;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
abstract class Model implements \Tk\Db\ModelInterface
{
    use ConfigTrait;


    /**
     * @var string
     */
    public static $MAPPER_APPEND = 'Map';


    /**
     * Get this object's DB mapper
     *
     * The Mapper class will be taken from this class's name if not supplied
     * By default the Database is attempted to be set from the Tk\Config object if it exists
     * Also the Default table name is generated from this object: EG: /App/Db/WebUser = 'webUser'
     * This would in-turn look for the mapper class /App/Db/WebUserMap
     * Change the self::$APPEND parameter to change the class append name
     * The method setDb() must be called after calling getMapper() if you do not wish to use the DB from the config
     *
     * @param string $mapperClass 
     * @param Pdo $db 
     * @return Mapper
     */
    static function createMapper($mapperClass = '', $db = null)
    {
        if (!$mapperClass)
            $mapperClass = get_called_class() . self::$MAPPER_APPEND;
        if (!preg_match('/'.self::$MAPPER_APPEND.'$/', $mapperClass))
            $mapperClass = $mapperClass . self::$MAPPER_APPEND;
        return $mapperClass::create($db);
    }

    /**
     * @return Mapper
     */
    public function getMapper()
    {
        return self::createMapper(get_class($this));
    }

    public function __clone()
    {
        if (property_exists($this, 'modified'))
            $this->modified = \Tk\Date::create();
        if (property_exists($this, 'created'))
            $this->modified = \Tk\Date::create();

        // This base class automatically clones attributes of type object or
        // array values of type object recursively.
        // Just inherit your own classes from this base class.
        $object_vars = get_object_vars($this);
        foreach ($object_vars as $attr_name => $attr_value) {
            if (is_object($this->$attr_name)) {
                $this->$attr_name = clone $this->$attr_name;
            } else if (is_array($this->$attr_name)) {
                // Note: This copies only one dimension arrays
                foreach ($this->$attr_name as &$attr_array_value) {
                    if (is_object($attr_array_value)) {
                        $attr_array_value = clone $attr_array_value;
                    }
                    unset($attr_array_value);
                }
            }
        }
        $this->setId(0);
    }

    /**
     * Get the model primary DB key, usually ID
     *
     * @return mixed
     */
    public function getId()
    {
        $pk = self::createMapper()->getPrimaryKeyProperty();
        if (property_exists($this, $pk)) {
            return $this->$pk;
        }
        return null;
    }

    /**
     * @param mixed $id
     * @return $this
     */
    private function setId($id)
    {
        $pk = self::createMapper()->getPrimaryKeyProperty();
        if (property_exists($this, $pk)) {
            $this->$pk = $id;
        }
        return $this;
    }

    /**
     * Insert the object into storage.
     * By default this is a database
     *
     * @return int The insert ID
     * @throws \Exception
     */
    public function insert()
    {
        $id = self::createMapper()->insert($this);
        if (!$this->getId())
            $this->setId($id);  // Has to be here cause of private property
        return $id;
    }

    /**
     * Update the object in storage
     *
     * @return int
     * @throws \Exception
     */
    public function update()
    {
        $r = self::createMapper()->update($this);
        return $r;
    }

    /**
     * A Utility method that checks the id and does and insert
     * or an update  based on the objects current state
     *
     * @throws \Exception
     */
    public function save()
    {
        self::createMapper()->save($this);
    }

    /**
     * Delete the object from the DB
     *
     * @return int
     * @throws \Exception
     */
    public function delete()
    {
        return self::createMapper()->delete($this);
    }

    /**
     * Return the deleted flag status of the object
     * 1 = deleted
     * 0 = not-deleted
     *
     * @return false
     */
    public function isDel()
    {
        if (isset($this->del)) {
            return $this->del;
        }
        return false;
    }

    /**
     * Returns the object id if it is greater than 0 or the nextInsertId if is 0
     *
     * @return int
     */
    public function getVolatileId()
    {
        if (!$this->getId()) {
            try {
                return self::createMapper()->getDb()->getNextInsertId(self::createMapper()->getTable());
            } catch (\Exception $e) {
                \Tk\Log::warning('Cannot get Model::getVolatileId() value, returning 0');
                return 0;
            }
        }
        return $this->getId();
    }

    /**
     * Override this to create a validation method to check your model for errors
     * Handy for use with forms and before saving the data to the DB
     *
     * @toto See if this is really needed. Maybe create an interface....
     */
    public function validate() { return array(); }


}