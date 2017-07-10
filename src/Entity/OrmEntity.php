<?php

namespace AntOrm\Entity;

use AntOrm\Common\Libraries\Hydrators\ConstructFromArrayOrJson;
use AntOrm\Entity\Helpers\EntityPreparer;

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

    public function __construct($params = null)
    {
        $params = $this->convertToArray($params);
        if (empty($params)) {
            return;
        }
        $wrapper  = EntityPreparer::getWrapper($this);
        $myParams = [];
        foreach ($params as $property => $param) {
            if (!isset($param)) {
                continue;
            }
            if (!$column = $wrapper->getMetaData()->getColumnByPropertyName($property)) {
                continue;
            }
            if (!$column->getRelated() || !$column->getRelated()->getWith() instanceof OrmEntity) {
                $myParams[$column->getName()] = $param;
                continue;
            }
            $className = '\\' . get_class($column->getRelated()->getWith());
            if ($column->getRelated()->hasOne()) {
                if (!is_array($param)) {
                    continue;
                }
                $myParams[$column->getName()] = new $className(current($param));
            } else {
                if (!is_array($param)) {
                    continue;
                }
                $myParams[$column->getName()] = [];
                foreach ($param as $data) {
                    if (!is_array($data)) {
                        continue;
                    }
                    $myParams[$column->getName()][] = new $className($data);
                }
            }
        }

        parent::__construct($myParams);
    }

    public function beforeUpdate()
    {
    }

    public function beforeInsert()
    {
    }

    public function beforeDelete()
    {
    }

    /**
     * @param bool $wasSuccess
     */
    public function afterUpdate($wasSuccess = true)
    {
    }

    /**
     * @param bool $wasSuccess
     */
    public function afterInsert($wasSuccess = true)
    {
    }

    /**
     * @param bool $wasSuccess
     */
    public function afterDelete($wasSuccess = true)
    {
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