<?php

namespace AntOrm\Entity;

use AntOrm\Common\Libraries\Hydrators\ConstructFromArrayOrJson;

abstract class OrmEntity extends ConstructFromArrayOrJson
{
    /**
     * @var string
     */
    public $table;

    /**
     * @var array
     */
    public $antOrmSearchParams = [];

    //public function __construct($params = null)
    //{
    //    $params = $this->convertToArray($params);
    //    if (empty($params)) {
    //        return;
    //    }
    //    $wrapper   = EntityPreparer::getWrapper($this);
    //    $tableName = $wrapper->getMetaData()->getTable()->getName();
    //    $myParams  = [];
    //    $related   = [];
    //    foreach ($wrapper->getMetaData()->getColumns() as $column) {
    //        if ($column->getRelated() && $column->getRelated()->getWith() instanceof OrmEntity) {
    //            if ($column->getRelated()->hasOne()) {
    //                $myParams[$column->getName()] =
    //            }
    //            $related[] = $column->getRelated()->getWith();
    //            continue;
    //        }
    //        $key = $tableName . '_' . $column->getName();
    //        if (empty($params[$key])) {
    //            continue;
    //        }
    //        $myParams[$column->getName()] = $params[$key];
    //        unset($params[$key]);
    //    }
    //
    //    parent::__construct($params);
    //}

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
        $result     = [];
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