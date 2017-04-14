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
        $parts->addSelect(
            "CONCAT(`{$tableName}`.`" .
            implode(
                " `{$tableName}`.`", $wrapper->getMetaData()->getTable()->getPrimaryProperties()
            )
            . "`) as {$prefix}{$ormPrimaryKey}"
        );
        foreach ($wrapper->getMetaData()->getColumns() as $column) {
            if (!$column->getRelated() || !$column->getRelated()->getWith() instanceof OrmEntity) {
                $parts->addSelect(
                    "`{$tableName}`.`{$column->getColumn()}` as {$prefix}{$column->getName()}"
                );
                continue;
            }
            $relatedWrapper = EntityPreparer::getWrapper($column->getRelated()->getWith());
            $joinTableName  = $relatedWrapper->getMetaData()->getTable()->getName();

            $relatedWrappers[$prefix . $column->getName()] = $relatedWrapper;
            if ($column->getRelated()->getBy()) {
                $mediator = $column->getRelated()->getBy();
                $parts->addJoin(
                    "LEFT JOIN ( SELECT `{$joinTableName}`.*, `{$mediator->getTable()}`.`{$mediator->getMyColumn()}` WHERE `{$mediator->getTable()}`.`{$mediator->getRelatedColumn()}` = `{$joinTableName}`.`{$column->getRelated()->getOnHisColumn()}`) as `{$joinTableName}` ON `{$joinTableName}`.`{$mediator->getMyColumn()}` = `{$tableName}`.`{$column->getRelated()->getOnMyColumn()}`"
                );
            } else {
                $parts->addJoin(
                    "LEFT JOIN `{$joinTableName}` ON `{$joinTableName}`.`{$column->getRelated()->getOnHisColumn()}` = `{$tableName}`.`{$column->getRelated()->getOnMyColumn()}`"
                );
            }
        }
        foreach ($relatedWrappers as $prefix => $relatedWrapper) {
            $parts = self::getRelatedParts($relatedWrapper, $parts, $prefix);
        }

        return $parts;
    }
}