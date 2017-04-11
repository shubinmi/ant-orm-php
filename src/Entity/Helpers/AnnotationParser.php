<?php

namespace AntOrm\Entity\Helpers;

use AntOrm\Entity\Objects\OrmProperty;
use AntOrm\Entity\Objects\OrmTable;

class AnnotationParser
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
                    return GetBindPattern::byOrm($docType);
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
        if (!$var = self::getVarContent($fullDoc)) {
            return null;
        }
        return GetBindPattern::byVar($var);
    }

    /**
     * @param string $doc
     *
     * @return string
     */
    public static function getVarContent($doc)
    {
        $varDoc = [];
        preg_match('~@var(.*?)\*~', $doc, $varDoc);
        if (empty($varDoc[1])) {
            return '';
        }
        return trim($varDoc[1]);
    }

    /**
     * @param string $doc
     *
     * @return string
     */
    public static function getOrmContent($doc)
    {
        $ormDoc = [];
        preg_match('~@orm{(.*?)}~U', $doc, $ormDoc);
        if (empty($ormDoc[1])) {
            return '';
        }
        return trim($ormDoc[1]);
    }
}