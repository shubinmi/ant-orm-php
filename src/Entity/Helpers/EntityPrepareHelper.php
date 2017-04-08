<?php

namespace AntOrm\Entity\Helpers;

use AntOrm\Entity\EntityMetaData;
use AntOrm\Entity\EntityProperty;
use AntOrm\Entity\EntityWrapper;
use AntOrm\Entity\Objects\OrmProperty;
use AntOrm\Entity\Objects\OrmTable;
use AntOrm\Entity\OrmEntity;

class EntityPrepareHelper
{
    /**
     * @param OrmEntity $entity
     *
     * @return EntityWrapper
     */
    public static function getWrapper(OrmEntity $entity)
    {
        $preparedProperties = EntityPrepareHelper::getEntityProperties($entity);
        $propertiesMetaData = EntityPrepareHelper::getPropertiesMeta($preparedProperties);
        $metaData           = new EntityMetaData();
        $metaData
            ->setTable(EntityPrepareHelper::getTableMeta($entity, $propertiesMetaData))
            ->setColumns($propertiesMetaData);
        $wrapper = new EntityWrapper();
        $wrapper->setEntity($entity)
            ->setPreparedProperties($preparedProperties)
            ->setMetaData($metaData);

        return $wrapper;
    }

    /**
     * @param OrmEntity $entity
     *
     * @return EntityProperty[]
     */
    public static function getEntityProperties(OrmEntity $entity)
    {
        $reflect    = new \ReflectionClass($entity);
        $properties = $reflect->getProperties(
            \ReflectionProperty::IS_PUBLIC |
            \ReflectionProperty::IS_PROTECTED
        );

        $result = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            if (property_exists(OrmEntity::class, $property->getName())) {
                continue;
            }
            $result[strtolower(trim($property->getName()))] =
                new EntityProperty($property->getName(), $property->getValue($entity), $property->getDocComment());
        }

        return $result;
    }

    /**
     * @param EntityProperty[] $preparedProperties
     *
     * @return OrmProperty[]
     */
    public static function getPropertiesMeta(array &$preparedProperties)
    {
        $propertiesMeta = [];
        foreach ($preparedProperties as &$preparedProperty) {
            if (!$propertyMeta = ParseDocHelper::getOrmPropertyByDoc($preparedProperty->doc)) {
                $propertyMeta = new OrmProperty();
            }
            $propertyMeta->setName($preparedProperty->name);
            if (!$propertyMeta->getColumn()) {
                $propertyMeta->setColumn(
                    strtolower(trim($preparedProperty->name))
                );
            }
            $propertiesMeta[$propertyMeta->getName()] = $propertyMeta;
            $preparedProperty->metaData               = $propertyMeta;
        }

        return $propertiesMeta;
    }

    /**
     * @param OrmEntity     $entity
     * @param OrmProperty[] $propertiesMetaData
     *
     * @return OrmTable
     */
    public static function getTableMeta(OrmEntity $entity, array $propertiesMetaData)
    {
        $reflector = new \ReflectionClass($entity);
        $doc       = $reflector->getDocComment();
        if (!$tableMeta = ParseDocHelper::getOrmTableByDoc($doc)) {
            $tableMeta = new OrmTable();
        }
        if ($entity->table) {
            $tableMeta->setTable($entity->table);
        } elseif (!$tableMeta->getName()) {
            $tableName = get_class($entity);
            $tableName = end(explode('\\', $tableName));
            $tableMeta->setTable(strtolower(trim($tableName)));
        }
        $primaryPropertiesNames = $tableMeta->getPrimaryProperties();
        if (!empty($primaryPropertiesNames)) {
            $primaryPropertiesNames = array_map(
                function ($v) {
                    return trim($v);
                },
                $primaryPropertiesNames
            );
            $tableMeta->setPrimaryProperties($primaryPropertiesNames);

            return $tableMeta;
        }
        foreach ($propertiesMetaData as $propertyMeta) {
            if ($propertyMeta->isPrimary()) {
                $tableMeta->addPrimaryProperty($propertyMeta->getName());
            }
        }

        return $tableMeta;
    }
}