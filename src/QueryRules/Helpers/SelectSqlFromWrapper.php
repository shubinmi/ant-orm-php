<?php

namespace AntOrm\QueryRules\Helpers;

use AntOrm\Entity\EntityWrapper;
use AntOrm\Entity\Helpers\EntityPreparer;
use AntOrm\Entity\OrmEntity;
use AntOrm\QueryRules\Sql\MySql;
use AntOrm\QueryRules\Sql\RelatedSelectSqlParts;

class SelectSqlFromWrapper
{
    public static function getRelatedParts(EntityWrapper $wrapper, RelatedSelectSqlParts &$parts, $selectPrefix = '')
    {
        $separator       = MySql::SELECT_PREFIX_SEPARATOR;
        $ormPrimaryKey   = MySql::SELECT_PRIMARY_KEY;
        $relatedWrappers = [];
        $tableName       = $wrapper->getMetaData()->getTable()->getName();
        $prefix          = ($selectPrefix ?: $tableName) . $separator;
        if (!$parts->getRootTable()) {
            $parts->setRootTable($tableName);
        }
        $parts->addSelect(
            "CONCAT(`{$tableName}`.`" .
            implode(
                " `{$tableName}`.`", $wrapper->getMetaData()->getTable()->getPrimaryProperties()
            )
            . "`) as {$prefix}{$ormPrimaryKey}"
        );
        foreach ($wrapper->getPreparedProperties() as $property) {
            $propertyMeta = $property->metaData;
            if (!$propertyMeta->getRelated() || !$propertyMeta->getRelated()->getWith() instanceof OrmEntity) {
                $parts->addSelect(
                    "`{$tableName}`.`{$propertyMeta->getColumn()}` as {$prefix}{$propertyMeta->getName()}"
                );
                continue;
            }
            $relatedWrapper = EntityPreparer::getWrapper($propertyMeta->getRelated()->getWith());
            $joinTableName  = $relatedWrapper->getMetaData()->getTable()->getName();
            if ($parts->hasJoin($joinTableName) || $joinTableName == $parts->getRootTable()) {
                continue;
            }
            $relatedWrappers[$prefix . $propertyMeta->getName()] = $relatedWrapper;
            if ($propertyMeta->getRelated()->getBy()) {
                $mediator = $propertyMeta->getRelated()->getBy();
                $parts->addNotExistedJoin(
                    $joinTableName,
                    "LEFT JOIN ( SELECT `{$joinTableName}`.*, `{$mediator->getTable()}`.`{$mediator->getMyColumn()}` FROM `{$joinTableName}`, `{$mediator->getTable()}` WHERE `{$mediator->getTable()}`.`{$mediator->getRelatedColumn()}` = `{$joinTableName}`.`{$propertyMeta->getRelated()->getOnHisColumn()}`) as `{$joinTableName}` ON `{$joinTableName}`.`{$mediator->getMyColumn()}` = `{$tableName}`.`{$propertyMeta->getRelated()->getOnMyColumn()}`"
                );
            } else {
                $parts->addNotExistedJoin(
                    $joinTableName,
                    "LEFT JOIN `{$joinTableName}` ON `{$joinTableName}`.`{$propertyMeta->getRelated()->getOnHisColumn()}` = `{$tableName}`.`{$propertyMeta->getRelated()->getOnMyColumn()}`"
                );
            }
        }
        foreach ($relatedWrappers as $prefix => $relatedWrapper) {
            $parts = self::getRelatedParts($relatedWrapper, $parts, $prefix);
        }

        return $parts;
    }
}