<?php

namespace AntOrm\QueryRules\Helpers;

use AntOrm\QueryRules\Sql\MySql;
use AntOrm\QueryRules\Sql\RelatedSelectSqlParts;

class ColumnNameParser
{
    /**
     * @param string                $columnName
     * @param string                $tableName
     * @param RelatedSelectSqlParts $selectParts
     *
     * @return string
     */
    public static function getEscaped($columnName, $tableName, RelatedSelectSqlParts $selectParts)
    {
        $concurrences = [];
        preg_match('~\((.*?)\)~U', $columnName, $concurrences);
        if (empty($concurrences[1])) {
            if (
            $column = $selectParts->findSelectedColumn(
                $tableName . MySql::SELECT_PREFIX_SEPARATOR . $columnName
            )
            ) {
                return $column;
            }
            return self::addQuotes($columnName, $tableName);
        }
        $withQuotes = self::addQuotes($concurrences[1], $tableName);

        return str_replace($concurrences[1], $withQuotes, $columnName);
    }

    /**
     * @param string $columnName
     * @param string $tableName
     *
     * @return string
     */
    private static function addQuotes($columnName, $tableName = '')
    {
        if ($tableName && strpos($columnName, '.') === false) {
            $columnName = $tableName . '.' . $columnName;
        }
        return '`' . implode('`.`', explode('.', trim($columnName))) . '`';
    }
}