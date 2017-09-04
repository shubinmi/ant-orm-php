<?php

namespace AntOrm\Entity\Helpers;

use AntOrm\Entity\EntityProperty;
use AntOrm\Entity\EntityWrapper;
use AntOrm\Entity\OrmEntity;

class WrappersLinking
{
    /**
     * @param EntityWrapper $me
     * @param EntityWrapper $to
     */
    public static function connect(EntityWrapper $me, EntityWrapper &$to)
    {
        foreach ($me->getPreparedProperties() as $fromProperty) {
            if (!$fromProperty->metaData->getRelated()) {
                continue;
            }
            if (
                $fromProperty->value instanceof OrmEntity
                && get_class($fromProperty->value) !== get_class($to->getEntity())
            ) {
                continue;
            }
            if (
                is_array($fromProperty->value)
                && current($fromProperty->value) instanceof OrmEntity
                && get_class(current($fromProperty->value)) !== get_class($to->getEntity())
            ) {
                continue;
            }
            foreach ($to->getPreparedProperties() as &$toProperty) {
                if ($fromProperty->metaData->getRelated()->getBy()) {
                    continue;
                }
                if ($fromProperty->metaData->getRelated()->getOnHisColumn() !== $toProperty->metaData->getColumn()) {
                    continue;
                }
                $toProperty->parentEntityWrapper = $me;
                return;
            }
            $to->setMyParent($me);
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