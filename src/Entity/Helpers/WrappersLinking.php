<?php

namespace AntOrm\Entity\Helpers;

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
            if (
                !$fromProperty->metaData->getRelated()
                || !$fromProperty->value instanceof OrmEntity
                || get_class($fromProperty->value) !== get_class($to->getEntity())
            ) {
                continue;
            }
            foreach ($to->getPreparedProperties() as &$toProperty) {
                if ($fromProperty->metaData->getRelated()->getOnHisColumn() !== $toProperty->metaData->getColumn()) {
                    continue;
                }
                $toProperty->linkedWrapper = $me;
                return;
            }
        }
    }
}