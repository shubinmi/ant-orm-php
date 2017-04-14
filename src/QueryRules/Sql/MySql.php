<?php

namespace AntOrm\QueryRules\Sql;

use AntOrm\Entity\EntityProperty;
use AntOrm\Entity\EntityWrapper;
use AntOrm\QueryRules\CrudDbInterface;
use AntOrm\QueryRules\Helpers\ColumnNameParser;
use AntOrm\QueryRules\Helpers\SelectSqlFromWrapper;
use AntOrm\QueryRules\QueryStructure;

class MySql implements CrudDbInterface
{
    const SELECT_PREFIX_SEPARATOR = '____';
    const SELECT_PRIMARY_KEY      = 'orm_primary_key';

    /**
     * @param EntityWrapper $wrapper
     *
     * @return QueryStructure
     */
    public function select(EntityWrapper $wrapper)
    {
        $query     = new QueryStructure();
        $searchSql = new SearchSql($wrapper->getEntity()->antOrmSearchParams);
        $sql       = $this->getSqlQuery($searchSql, $query, $wrapper);
        $query->setQuery($sql);

        return $query;
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return QueryStructure
     */
    public function insert(EntityWrapper $wrapper)
    {
        $query       = new QueryStructure();
        $properties  = $wrapper->getPreparedProperties();
        $queryValues = $queryColumns = [];
        foreach ($properties as $property) {
            /** @var EntityProperty $property */
            if (
                (!isset($property->value) && !$property->linkedWrapper)
                || $property->metaData->getRelated()
            ) {
                continue;
            }
            if ($property->linkedWrapper) {
                if (!$linkQuery = $this->getLinkedInsertColumn($property)) {
                    continue;
                }
                $query->setBindParams(
                    array_merge(
                        $query->getBindParams(), $linkQuery->getBindParams()
                    )
                );
                $query->setBindPatterns(
                    array_merge(
                        $query->getBindPatterns(), $linkQuery->getBindPatterns()
                    )
                );
                $queryValues[] = "({$linkQuery->getQuery()})";
            } else {
                $query->addBindParam($property->value);
                $query->addBindPattern($property->getBindTypePattern());
                $queryValues[] = '?';
            }
            $queryColumns[] = $property->metaData->getColumn();
        }
        $queryColumns = implode('`,`', $queryColumns);
        $queryValues  = implode(',', $queryValues);
        if (!empty($queryColumns)) {
            $queryColumns = '`' . $queryColumns . '`';
        }
        $tableName = $wrapper->getMetaData()->getTable()->getName();
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "INSERT INTO `{$tableName}` ({$queryColumns}) VALUES ({$queryValues})";
        $query->setQuery($sql);

        return $query;
    }

    /**
     * @param EntityProperty $entityProperty
     *
     * @return QueryStructure|null
     */
    public function getLinkedInsertColumn(EntityProperty $entityProperty)
    {
        $properties   = $entityProperty->linkedWrapper->getPreparedProperties();
        $selectTable  = $entityProperty->linkedWrapper->getMetaData()->getTable()->getName();
        $query        = new QueryStructure();
        $selectColumn = '';
        $where        = '';
        /** @var EntityProperty $property */
        foreach ($properties as $property) {
            if (
                $property->metaData->getRelated()
                && $property->metaData->getRelated()->getOnHisColumn() === $entityProperty->metaData->getColumn()
            ) {
                $selectColumn = $property->metaData->getRelated()->getOnMyColumn();
            }
            if (!isset($property->value) || $property->metaData->getRelated()) {
                continue;
            }
            $query->addBindParam($property->value);
            $query->addBindPattern($property->getBindTypePattern());
            if ($where) {
                $where .= ' AND ';
            }
            $where .= " `{$property->metaData->getColumn()}` = ? ";
        }
        if (empty($selectColumn) || empty($where)) {
            return null;
        }
        /** @noinspection SqlDialectInspection */
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "SELECT `{$selectColumn}` FROM `{$selectTable}` WHERE {$where} ORDER BY `{$selectColumn}` DESC LIMIT 1";
        $query->setQuery($sql);

        return $query;
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return QueryStructure
     */
    public function update(EntityWrapper $wrapper)
    {
        $query                  = new QueryStructure();
        $properties             = $wrapper->getPreparedProperties();
        $queryColumns           = '';
        $primaryColumns         = $wrapper->getMetaData()->getTable()->getPrimaryProperties();
        $declaredPrimaryColumns = !empty($primaryColumns);
        $where                  = '';
        $whereBindParams        = [];
        $whereBindPatterns      = [];
        foreach ($properties as $property) {
            /** @var EntityProperty $property */
            if (!isset($property->value) || $property->metaData->getRelated()) {
                continue;
            }
            if (!$declaredPrimaryColumns && empty($where)) {
                if (strpos($property->name, 'id') !== false) {
                    $where               .= "`{$property->metaData->getColumn()}` = ?";
                    $whereBindParams[]   = $property->value;
                    $whereBindPatterns[] = $property->getBindTypePattern();
                    continue;
                }
            }
            if ($declaredPrimaryColumns && in_array(trim($property->name), $primaryColumns)) {
                if (!empty($where)) {
                    $where .= ' AND ';
                }
                $where               .= "`{$property->metaData->getColumn()}` = ?";
                $whereBindParams[]   = $property->value;
                $whereBindPatterns[] = $property->getBindTypePattern();
                continue;
            }
            if (!empty($queryColumns)) {
                $queryColumns .= ', ';
            }
            $queryColumns .= "`{$property->metaData->getColumn()}` = ?";
            $query->addBindParam($property->value);
            $query->addBindPattern($property->getBindTypePattern());
        }
        if (!$where) {
            $where = '1 = 2';
        } else {
            $query->setBindParams(array_merge($query->getBindParams(), $whereBindParams));
            $query->setBindPatterns(array_merge($query->getBindPatterns(), $whereBindPatterns));
        }
        $tableName = $wrapper->getMetaData()->getTable()->getName();
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "UPDATE `{$tableName}` SET {$queryColumns} WHERE {$where}";
        $query->setQuery($sql);

        return $query;
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return QueryStructure
     */
    public function delete(EntityWrapper $wrapper)
    {
        $query                  = new QueryStructure();
        $properties             = $wrapper->getPreparedProperties();
        $primaryColumns         = $wrapper->getMetaData()->getTable()->getPrimaryProperties();
        $declaredPrimaryColumns = !empty($primaryColumns);
        $where                  = '';
        foreach ($properties as $property) {
            if (!isset($property->value) || $property->metaData->getRelated()) {
                continue;
            }
            if (!$declaredPrimaryColumns && empty($where)) {
                if (strpos($property->metaData->getColumn(), 'id') !== false) {
                    $where .= "`{$property->metaData->getColumn()}` = ?";
                    $query->addBindParam($property->value);
                    $query->addBindPattern($property->getBindTypePattern());
                }
            }
            if (
                $declaredPrimaryColumns
                && in_array(trim($property->metaData->getColumn()), $primaryColumns)
            ) {
                if (!empty($where)) {
                    $where .= ' AND ';
                }
                $where .= "`{$property->metaData->getColumn()}` = ?";
                $query->addBindParam($property->value);
                $query->addBindPattern($property->getBindTypePattern());
            }
        }
        if (!$where) {
            $where = '1 = 2';
        }
        $tableName = $wrapper->getMetaData()->getTable()->getName();
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "DELETE FROM `{$tableName}` WHERE {$where}";
        $query->setQuery($sql);

        return $query;
    }

    /**
     * @param SearchSql      $searchSql
     * @param QueryStructure $queryStructure
     * @param EntityWrapper  $wrapper
     *
     * @return string
     */
    private function getSqlQuery(SearchSql $searchSql, QueryStructure &$queryStructure, EntityWrapper $wrapper)
    {
        $selectParts = SelectSqlFromWrapper::getRelatedParts($wrapper, new RelatedSelectSqlParts());
        $selected    = implode(', ', $selectParts->getSelects());
        $joined      = implode(' ', $selectParts->getJoins());
        $distinct    = $searchSql->distinct ? 'DISTINCT' : '';
        $table       = $wrapper->getMetaData()->getTable()->getName();
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "SELECT {$distinct} {$selected} FROM `{$table}` {$joined}";
        if (!empty($searchSql->join)) {
            $sql .= ' ' . $this->getQueryJoin($searchSql) . ' ';
        }
        $sql .= ' WHERE ';
        $sql .= !$searchSql->where ? 1 : $this->getQueryWhere($wrapper, $searchSql->where, $queryStructure);
        if ($searchSql->groupBy) {
            $sql .= ' GROUP BY ' . $searchSql->groupBy;
        }
        if ($searchSql->having) {
            $sql .= ' HAVING ' . $searchSql->having;
        }
        if ($searchSql->orderBy) {
            $searchSql->orderBy = str_replace('`', '', $searchSql->orderBy);
            $orders             = array_values(array_filter(explode(',', $searchSql->orderBy)));
            $sql                .= ' ORDER BY ';
            foreach ($orders as $order) {
                $orderBy  = array_values(array_filter(explode(' ', $order)));
                $orderSql = strpos($orderBy[0], '.') === false ? "`{$orderBy[0]}`" : $orderBy[0];
                $sql      .= $orderSql . ' ';
                if (!empty($orderBy[1])) {
                    $sql .= $orderBy[1] . ' ';
                }
                $sql .= ',';
            }
            $sql = substr($sql, 0, -1);
        }
        if ($searchSql->limit) {
            $sql .= ' LIMIT ';
            $sql .= $searchSql->offset
                ?
                (int)$searchSql->offset . ', ' . (int)$searchSql->limit
                :
                (int)$searchSql->limit;
        }

        return $sql;
    }

    /**
     * @param SearchSql $searchSql
     *
     * @return string
     */
    private function getQueryJoin(SearchSql $searchSql)
    {
        $join = '';
        foreach ($searchSql->join as $joinOperator => $joinConditions) {
            if (is_string($joinConditions)) {
                $join .= " {$joinOperator} " . $joinConditions . ' ';
                continue;
            }
            if (!is_array($joinConditions)) {
                continue;
            }
            foreach ($joinConditions as $joinCondition) {
                $join .= " {$joinOperator} ";
                if (is_string($joinCondition)) {
                    $join .= " {$joinCondition} ";
                    continue;
                }
                if (is_array($joinCondition)) {
                    $table = $on = '';
                    foreach ($joinCondition as $joinConditionKey => $joinConditionValue) {
                        if (strtolower($joinConditionKey) == 'table') {
                            $table = $joinConditionValue;
                        } elseif (strtolower($joinConditionKey) == 'on') {
                            $on = $joinConditionValue;
                        }
                    }
                    if ($table && $on) {
                        $join .= " {$table} ON {$on} ";
                    }
                    continue;
                }
            }
        }

        return $join;
    }

    /**
     * @param EntityWrapper  $wrapper
     * @param array          $whereParams
     * @param QueryStructure $queryStructure
     * @param string         $logicOperator
     *
     * @return string
     */
    private function getQueryWhere(
        EntityWrapper $wrapper, array $whereParams, QueryStructure &$queryStructure, $logicOperator = 'AND'
    ) {
        $sql              = '';
        $iterateNumber    = 0;
        $countWhereParams = count($whereParams);
        foreach ($whereParams as $key => $param) {
            $iterateNumber++;
            if (in_array(strtolower($key), ['or', 'and']) && is_array($param)) {
                $sql .= ' ( ' . $this->getQueryWhere($wrapper, $param, $queryStructure, $key) . ' ) ';
            } elseif (is_array($param)) {
                $sql .= ' ( ' . $this->getQueryWhere($wrapper, $param, $queryStructure, $logicOperator) . ' ) ';
            } else {
                $sql .= $this->getPartOfCondition($key, $param, $queryStructure, $wrapper);
            }
            if ($iterateNumber < $countWhereParams) {
                $sql .= ' ' . $logicOperator . ' ';
            }
        }

        return $sql;
    }

    /**
     * @param string         $columnNameWithCompareOperator
     * @param string         $columnValue
     * @param QueryStructure $queryStructure
     *
     * @param EntityWrapper  $wrapper
     *
     * @return string
     */
    private function getPartOfCondition(
        $columnNameWithCompareOperator, $columnValue,
        QueryStructure &$queryStructure, EntityWrapper $wrapper
    ) {
        if (!$conditionParts = $this->getColumnNameAndCompareOperation($columnNameWithCompareOperator)) {
            return '';
        }
        $columnName          = $conditionParts['columnName'];
        $operator            = $conditionParts['compareOperator'];
        $escapedColumnName   = ColumnNameParser::getEscaped(
            $columnName, $wrapper->getMetaData()->getTable()->getName()
        );
        $comparisonOperators = ['in', 'is', 'is not', 'not in', 'strcmp', 'interval', 'least', 'not between'];
        if (in_array(strtolower($operator), $comparisonOperators)) {
            return " {$escapedColumnName} {$operator} {$columnValue} ";
        }
        $properties = $wrapper->getPreparedProperties();
        if (empty($properties[$columnName])) {
            return " $escapedColumnName {$operator} '{$columnValue}' ";
        }
        $queryStructure->addBindPattern($properties[$columnName]->getBindTypePattern());
        $queryStructure->addBindParam($columnValue);

        return " $escapedColumnName {$operator} ? ";
    }

    /**
     * @param string $columnNameWithCompareOperator
     *
     * @return array|null
     */
    private function getColumnNameAndCompareOperation($columnNameWithCompareOperator)
    {
        if (empty(trim($columnNameWithCompareOperator))) {
            return null;
        }
        $params     = explode(' ', trim($columnNameWithCompareOperator));
        $columnName = empty($params[0]) ? '' : $params[0];
        unset($params[0]);
        $operator = empty($params[1]) ? '=' : implode(' ', $params);

        $columnName = str_replace('`', '', $columnName);

        return [
            'columnName'      => trim($columnName),
            'compareOperator' => strtolower(trim($operator))
        ];
    }
}