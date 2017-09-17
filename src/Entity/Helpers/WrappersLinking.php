<?php

namespace AntOrm\Entity\Helpers;

use AntOrm\Entity\EntityProperty;
use AntOrm\Entity\EntityWrapper;
use AntOrm\Entity\OrmEntity;

class WrappersLinking
{
    /**
     * @param EntityWrapper $root
     * @param EntityWrapper $kid
     */
    public static function connect(EntityWrapper $root, EntityWrapper &$kid)
    {
        foreach ($root->getPreparedProperties() as $rootProperty) {
            if (!$rootProperty->metaData->getRelated()) {
                continue;
            }
            if (
                $rootProperty->value instanceof OrmEntity
                && get_class($rootProperty->value) !== get_class($kid->getEntity())
            ) {
                continue;
            }
            if (
                is_array($rootProperty->value)
                && current($rootProperty->value) instanceof OrmEntity
                && get_class(current($rootProperty->value)) !== get_class($kid->getEntity())
            ) {
                continue;
            }
            foreach ($kid->getPreparedProperties() as &$kidProperty) {
                if ($rootProperty->metaData->getRelated()->getBy()) {
                    continue;
                }
                if ($rootProperty->metaData->getRelated()->getOnHisColumn() !== $kidProperty->metaData->getColumn()) {
                    continue;
                }
                $kidProperty->parentEntityWrapper = $root;
                return;
            }
            $kid->setMyParent($root);
        }
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return EntityProperty|null
     */
    public static function getRelatedParentPropertyForMany2Many(EntityWrapper $wrapper)
    {
        foreach ($wrapper->getMyParent()->getPreparedProperties() as $property) {
            if (
                $property->metaData->getRelated()
                && $property->metaData->getRelated()->getBy()
                && $property->metaData->getRelated()->getWith() instanceof OrmEntity
                && get_class($property->metaData->getRelated()->getWith()) == get_class($wrapper->getEntity())
            ) {
                return clone $property;
            }
        }
        return null;
    }
}