<?php
namespace Tk\DataMap\Form;


/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2016 Michael Mifsud
 */
class Date extends Iface
{
    /**
     * Valid date format `31/12/2000`
     */
    const FORMAT_DATE = 'd/m/Y';

    /**
     * Valid date format `31/12/2000 23:59`
     */
    const FORMAT_DATETIME = 'd/m/Y H:i';

    /**
     * Valid date format `23:59`
     */
    const FORMAT_TIME = 'H:i';


    /**
     * @var string
     */
    protected  $format = self::FORMAT_DATE;

    /**
     * @param $format
     * @return $this
     */
    public function setDateFormat($format)
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Map an array column value to an object property value
     *
     * @param array $row
     * @param string $columnName
     * @return \DateTime|null
     */
    public function toPropertyValue($row, $columnName)
    {
        $value = parent::toPropertyValue($row, $columnName);
        if (!$value) $value = null;
        if ($value != null && !$value instanceof \DateTime) {
            $value = \Tk\Date::createFormDate($value, null, $this->format);
        }

        return $value;
    }

    /**
     * Map an object property value to an array column value
     *
     * @param mixed $object
     * @param string $propertyName
     * @return string|null
     */
    public function toColumnValue($object, $propertyName)
    {
        $value = parent::toColumnValue($object, $propertyName);
        if (!$value) $value = null;
        if ($value != null && $value instanceof \DateTime) {
            return $value->format($this->format);
        }
        return $value;
    }

}

