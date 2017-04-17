<?php

namespace AntOrm\Entity\Helpers;

use AntOrm\Entity\EntityProperty;
use AntOrm\Entity\Objects\OrmProperty;
use AntOrm\Entity\Objects\OrmTable;

class OrmMetaConstructor
{
    /**
     * @param EntityProperty $property
     *
     * @return OrmProperty
     */
    public static function byEntityProperty(EntityProperty $property)
    {
        if (!$meta = AnnotationParser::getOrmPropertyByDoc($property->doc)) {
            $meta = new OrmProperty();
        }
        $meta->setName($property->name);
        if (!$meta->getColumn()) {
            $meta->setColumn(
                strtolower(trim($property->name))
            );
        }
        if (!$related = $meta->getRelated()) {
            return $meta;
        }
        $type = AnnotationParser::getVarContent($property->doc);
        if (!$related->getWith() && $type) {
            $preparedType = current(
                explode(
                    ' ',
                    trim(
                        str_replace(
                            ['null|', '|null', 'null |', '| null', '[', ']'],
                            '',
                            $type
                        )
                    )
                )
            );
            if (
            !in_array(
                strtolower($preparedType),
                ['string', 'int', 'integer', 'boolean', 'bool', 'string', 'float']
            )
            ) {
                $related->setWith($preparedType);
            }
        }
        if (!$related->getWith()) {
            return $meta;
        }
        if (!$related->hasOne() && !$related->hasMany()) {
            if (strpos($type, '[]') === false) {
                $related->setAs('hasOne');
            } else {
                $related->setAs('hasMany');
            }
        }

        return $meta;
    }

    /**
     * @param OrmProperty $property
     * @param OrmTable    $table
     */
    public static function setRelationColumns(OrmProperty &$property, OrmTable $table)
    {
        if (!$property->getRelated()) {
            return;
        }
        $related = &$property->getRelated();
        $column  = 'id';
        if (!empty($table->getPrimaryProperties())) {
            $column = $table->getPrimaryProperties()[0];
        }
        if (!$related->getOnMyColumn()) {
            $related->setOnMyColumn($column);
        }
        if (!$related->getOnHisColumn()) {
            $related->setOnHisColumn($table->getName() . '_' . $column);
        }
    }
}