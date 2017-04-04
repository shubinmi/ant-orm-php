<?php

namespace AntOrm\QueryRules\Sql;

use AntOrm\Entity\EntityWrapper;
use AntOrm\QueryRules\CrudDbInterface;
use AntOrm\QueryRules\Helpers\ColumnNameParser;
use AntOrm\QueryRules\QueryPrepareInterface;
use AntOrm\QueryRules\QueryStructure;

class MySql implements QueryPrepareInterface, CrudDbInterface
{
    const TABLE      = 'table';
    const TYPES      = 'types';
    const PARAMETERS = 'parameters';
    const COLUMNS    = 'columns';

    /**
     * @var EntityWrapper
     */
    private $wrapper;

    /**
     * @param string        $operation
     * @param EntityWrapper $wrapper
     *
     * @return QueryStructure
     * @throws \Exception
     */
    public function prepare($operation, EntityWrapper $wrapper)
    {
        if (!in_array($operation, get_class_methods('AntOrm\QueryRules\CrudDbInterface'))) {
            throw new \Exception('Incorrect $operator value = ' . $operation);
        }
        $this->wrapper = $wrapper;

        return $this->$operation();
    }

    /**
     * @return QueryStructure
     */
    public function select()
    {
        $query     = new QueryStructure();
        $searchSql = new SearchSql($this->wrapper->getEntity()->antOrmSearchParams);
        $sql       = $this->getSqlQuery($searchSql, $query);
        $query->setQuery($sql);

        return $query;
    }

    /**
     * @return QueryStructure
     */
    public function insert()
    {
        $query       = new QueryStructure();
        $properties  = $this->wrapper->getPreparedProperties();
        $queryValues = $queryColumns = [];
        foreach ($properties as $property) {
            if (isset($property->value)) {
                $query->addBindParam($property->value);
                $query->addBindPattern($property->getTypePatternByDoc());
                $queryValues[]  = '?';
                $queryColumns[] = $property->name;
            }
        }
        $queryColumns = implode('`,`', $queryColumns);
        $queryValues  = implode(',', $queryValues);
        if (!empty($queryColumns)) {
            $queryColumns = '`' . $queryColumns . '`';
        }
        $tableName = $this->wrapper->getMetaData()->getTable()->getName();
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "INSERT INTO `{$tableName}` ({$queryColumns}) VALUES ({$queryValues})";
        $query->setQuery($sql);

        return $query;
    }

    /**
     * @return QueryStructure
     */
    public function update()
    {
        $query                  = new QueryStructure();
        $properties             = $this->wrapper->getPreparedProperties();
        $queryColumns           = '';
        $primaryColumns         = $this->wrapper->getMetaData()->getTable()->getPrimaryProperties();
        $declaredPrimaryColumns = !empty($primaryColumns);
        $where                  = '';
        $whereBindParams        = [];
        $whereBindPatterns      = [];
        foreach ($properties as $property) {
            if (isset($property->value)) {
                if (!$declaredPrimaryColumns && empty($where)) {
                    if (strpos($property->name, 'id') !== false) {
                        $where               .= "`{$property->name}` = ?";
                        $whereBindParams[]   = $property->value;
                        $whereBindPatterns[] = $property->getTypePatternByDoc();
                        continue;
                    }
                }
                if ($declaredPrimaryColumns && in_array(trim($property->name), $primaryColumns)) {
                    if (!empty($where)) {
                        $where .= ' AND ';
                    }
                    $where               .= "`{$property->name}` = ?";
                    $whereBindParams[]   = $property->value;
                    $whereBindPatterns[] = $property->getTypePatternByDoc();
                    continue;
                }
                if (!empty($queryColumns)) {
                    $queryColumns .= ', ';
                }
                $queryColumns .= "`{$property->name}` = ?";
                $query->addBindParam($property->value);
                $query->addBindPattern($property->getTypePatternByDoc());
            }
        }
        if (!$where) {
            $where = '1 = 2';
        } else {
            $query->setBindParams(array_merge($query->getBindParams(), $whereBindParams));
            $query->setBindPatterns(array_merge($query->getBindPatterns(), $whereBindPatterns));
        }
        $tableName = $this->wrapper->getMetaData()->getTable()->getName();
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "UPDATE `{$tableName}` SET {$queryColumns} WHERE {$where}";
        $query->setQuery($sql);

        return $query;
    }

    /**
     * @return QueryStructure
     */
    public function delete()
    {
        $query                  = new QueryStructure();
        $properties             = $this->wrapper->getPreparedProperties();
        $primaryColumns         = $this->wrapper->getMetaData()->getTable()->getPrimaryProperties();
        $declaredPrimaryColumns = !empty($primaryColumns);
        $where                  = '';
        foreach ($properties as $property) {
            if (isset($property->value)) {
                if (!$declaredPrimaryColumns && empty($where)) {
                    if (strpos($property->name, 'id') !== false) {
                        $where .= "`$property->name` = ?";
                        $query->addBindParam($property->value);
                        $query->addBindPattern($property->getTypePatternByDoc());
                    }
                }
                if ($declaredPrimaryColumns && in_array(trim($property->name), $primaryColumns)) {
                    if (!empty($where)) {
                        $where .= ' AND ';
                    }
                    $where .= "`$property->name` = ?";
                    $query->addBindParam($property->value);
                    $query->addBindPattern($property->getTypePatternByDoc());
                }
            }
        }
        if (!$where) {
            $where = '1 = 2';
        }
        $tableName = $this->wrapper->getMetaData()->getTable()->getName();
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "DELETE FROM `{$tableName}` WHERE {$where}";
        $query->setQuery($sql);

        return $query;
    }

    /**
     * @param SearchSql      $searchSql
     * @param QueryStructure $queryStructure
     *
     * @return string
     */
    private function getSqlQuery(SearchSql $searchSql, QueryStructure &$queryStructure)
    {
        $distinct = $searchSql->distinct ? 'DISTINCT' : '';
        $table    = $this->wrapper->getMetaData()->getTable()->getName();
        $sql      = 'SELECT ' . $distinct . " `{$table}`.* FROM `{$table}`";
        if (!empty($searchSql->join)) {
            $sql .= ' ' . $this->getQueryJoin($searchSql) . ' ';
        }
        $sql .= ' WHERE ';
        $sql .= !$searchSql->where ? 1 : $this->getQueryWhere($searchSql->where, $queryStructure);
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
     * @param array          $whereParams
     * @param QueryStructure $queryStructure
     * @param string         $logicOperator
     *
     * @return string
     */
    private function getQueryWhere(array $whereParams, QueryStructure &$queryStructure, $logicOperator = 'AND')
    {
        $sql              = '';
        $iterateNumber    = 0;
        $countWhereParams = count($whereParams);
        foreach ($whereParams as $key => $param) {
            $iterateNumber++;
            if (in_array(strtolower($key), ['or', 'and']) && is_array($param)) {
                $sql .= ' ( ' . $this->getQueryWhere($param, $queryStructure, $key) . ' ) ';
            } elseif (is_array($param)) {
                $sql .= ' ( ' . $this->getQueryWhere($param, $queryStructure, $logicOperator) . ' ) ';
            } else {
                $sql .= $this->getPartOfCondition($key, $param, $queryStructure);
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
     * @return string
     */
    private function getPartOfCondition($columnNameWithCompareOperator, $columnValue, QueryStructure &$queryStructure)
    {
        if (!$conditionParts = $this->getColumnNameAndCompareOperation($columnNameWithCompareOperator)) {
            return '';
        }
        $columnName          = $conditionParts['columnName'];
        $operator            = $conditionParts['compareOperator'];
        $escapedColumnName   = ColumnNameParser::getEscaped(
            $columnName, $this->wrapper->getMetaData()->getTable()->getName()
        );
        $comparisonOperators = ['in', 'is', 'is not', 'not in', 'strcmp', 'interval', 'least', 'not between'];
        if (in_array(strtolower($operator), $comparisonOperators)) {
            return " {$escapedColumnName} {$operator} {$columnValue} ";
        }
        $properties = $this->wrapper->getPreparedProperties();
        if (empty($properties[$columnName])) {
            return " $escapedColumnName {$operator} '{$columnValue}' ";
        }
        $queryStructure->addBindPattern($properties[$columnName]->getTypePatternByDoc());
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