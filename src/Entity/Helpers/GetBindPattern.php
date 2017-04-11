<?php

namespace AntOrm\Entity\Helpers;

use AntOrm\Entity\EntityProperty;

class GetBindPattern
{
    /**
     * @param string $type
     *
     * @return null|string
     */
    public static function byVar($type)
    {
        if (strpos($type, 'int') !== false) {
            return EntityProperty::BIND_TYPE_INTEGER;
        } elseif (strpos($type, 'float') !== false) {
            return EntityProperty::BIND_TYPE_DOUBLE;
        } elseif (strpos($type, 'bool') !== false) {
            return EntityProperty::BIND_TYPE_INTEGER;
        } elseif (strpos($type, 'double') !== false) {
            return EntityProperty::BIND_TYPE_DOUBLE;
        }

        return null;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    public static function byOrm($type)
    {
        if (strpos($type, 'int') !== false) {
            return EntityProperty::BIND_TYPE_INTEGER;
        } elseif (strpos($type, 'float') !== false) {
            return EntityProperty::BIND_TYPE_DOUBLE;
        } elseif (strpos($type, 'decimal') !== false) {
            return EntityProperty::BIND_TYPE_DOUBLE;
        } elseif (strpos($type, 'numeric') !== false) {
            return EntityProperty::BIND_TYPE_DOUBLE;
        } elseif (strpos($type, 'real') !== false) {
            return EntityProperty::BIND_TYPE_DOUBLE;
        } elseif (strpos($type, 'double') !== false) {
            return EntityProperty::BIND_TYPE_DOUBLE;
        } elseif (strpos($type, 'bool') !== false) {
            return EntityProperty::BIND_TYPE_INTEGER;
        } elseif (strpos($type, 'binary') !== false) {
            return EntityProperty::BIND_TYPE_BLOB;
        }

        return EntityProperty::BIND_TYPE_STRING;
    }
}