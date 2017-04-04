<?php

namespace AntOrm\QueryRules\Helpers;

class ColumnNameParser
{
    /**
     * @param string $columnName
     * @param string $tableName
     *
     * @return string
     */
    public static function getEscaped($columnName, $tableName = '')
    {
        $concurrences = [];
        preg_match('~\((.*?)\)~U', $columnName, $concurrences);
        if (empty($concurrences[1])) {
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