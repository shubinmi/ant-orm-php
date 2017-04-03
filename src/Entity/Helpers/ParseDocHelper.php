<?php

namespace AntOrm\Entity\Helpers;

use AntOrm\Entity\EntityProperty;
use AntOrm\Entity\Objects\OrmProperty;
use AntOrm\Entity\Objects\OrmTable;

class ParseDocHelper
{
    /**
     * @param string $doc
     *
     * @return OrmTable|null
     */
    public static function getOrmTableByDoc($doc)
    {
        if (!$ormDoc = self::getOrmContent($doc)) {
            return null;
        }
        return new OrmTable('{' . $ormDoc . '}');
    }

    /**
     * @param string $doc
     *
     * @return OrmProperty|null
     */
    public static function getOrmPropertyByDoc($doc)
    {
        if (!$ormDoc = self::getOrmContent($doc)) {
            return null;
        }
        return new OrmProperty('{' . $ormDoc . '}');
    }

    /**
     * @param string $fullDoc
     *
     * @return null|string
     */
    public static function getBindTypeByOrmAnnotation($fullDoc)
    {
        if ($ormDoc = self::getOrmPropertyByDoc($fullDoc)) {
            try {
                if ($ormDoc->getType()) {
                    $docType = trim($ormDoc->getType());
                    if (strpos($docType, 'int') !== false) {
                        return EntityProperty::BIND_TYPE_INTEGER;
                    } elseif (strpos($docType, 'float') !== false) {
                        return EntityProperty::BIND_TYPE_DOUBLE;
                    } elseif (strpos($docType, 'decimal') !== false) {
                        return EntityProperty::BIND_TYPE_DOUBLE;
                    } elseif (strpos($docType, 'numeric') !== false) {
                        return EntityProperty::BIND_TYPE_DOUBLE;
                    } elseif (strpos($docType, 'real') !== false) {
                        return EntityProperty::BIND_TYPE_DOUBLE;
                    } elseif (strpos($docType, 'double') !== false) {
                        return EntityProperty::BIND_TYPE_DOUBLE;
                    } elseif (strpos($docType, 'bool') !== false) {
                        return EntityProperty::BIND_TYPE_INTEGER;
                    } elseif (strpos($docType, 'binary') !== false) {
                        return EntityProperty::BIND_TYPE_BLOB;
                    } else {
                        return EntityProperty::BIND_TYPE_STRING;
                    }
                }
            } catch (\Exception $e) {
            }
        }

        return null;
    }

    /**
     * @param string $fullDoc
     *
     * @return null|string
     */
    public static function getBindTypeByVarDoc($fullDoc)
    {
        $varDoc = [];
        preg_match('~@var(.*?)\*~', $fullDoc, $varDoc);
        if (!empty($varDoc[1])) {
            $varDoc = trim($varDoc[1]);
            if (strpos($varDoc, 'int') !== false) {
                return EntityProperty::BIND_TYPE_INTEGER;
            } elseif (strpos($varDoc, 'float') !== false) {
                return EntityProperty::BIND_TYPE_DOUBLE;
            } elseif (strpos($varDoc, 'bool') !== false) {
                return EntityProperty::BIND_TYPE_INTEGER;
            } elseif (strpos($varDoc, 'double') !== false) {
                return EntityProperty::BIND_TYPE_DOUBLE;
            }
        }

        return null;
    }

    /**
     * @param string $doc
     *
     * @return string
     */
    private static function getOrmContent($doc)
    {
        $ormDoc = [];
        preg_match('~@orm{(.*?)}~U', $doc, $ormDoc);
        if (empty($ormDoc[1])) {
            return '';
        }
        return trim($ormDoc[1]);
    }
}