<?php

namespace AntOrm\Entity;

abstract class CoreEntity
{
    /**
     * @var string
     */
    public $table;

    /**
     * @var array
     */
    public $antOrmSearchParams;

    /**
     * @param array|\stdClass $properties
     */
    public function __construct($properties = [])
    {
        if ($properties instanceof \stdClass) {
            $properties = (array)$properties;
        }
        if (is_array($properties)) {
            foreach ($properties as $property => $value) {
                if (property_exists($this, $property)) {
                    $this->{$property} = $value;
                }
            }
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $reflect    = new \ReflectionClass($this);
        $properties = $reflect->getProperties(
            \ReflectionProperty::IS_PUBLIC |
            \ReflectionProperty::IS_PROTECTED |
            \ReflectionProperty::IS_PRIVATE
        );

        $result = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            if (property_exists(get_class(), $property->getName())) {
                continue;
            }
            $result[$property->getName()] = $property->getValue($this);
        }

        return $result;
    }

    /**
     * @return string
     */
    public static function className()
    {
        return get_called_class();
    }
}