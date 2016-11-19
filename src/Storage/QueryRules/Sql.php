<?php

namespace AntOrm\Storage\QueryRules;

use AntOrm\Entity\EntityProperty;
use AntOrm\Repository\CoreRepository;
use AntOrm\Entity\SearchSql;

class Sql implements QueryRuleInterface
{
    const TABLE      = 'table';
    const TYPES      = 'types';
    const PARAMETERS = 'parameters';
    const COLUMNS    = 'columns';

    /**
     * @var array
     */
    private $query;

    /**
     * @var array
     */
    private $columnType;

    /**
     * @param string           $operation
     * @param EntityProperty[] $properties
     *
     * @return array
     */
    public function prepare($operation, array $properties)
    {
        $query = [self::TABLE => '', self::TYPES => [], self::PARAMETERS => [], self::COLUMNS => []];
        foreach ($properties as $property) {
            if (empty($property)) {
                continue;
            }
            if ($property->name == self::TABLE) {
                $query[self::TABLE] = $property->value;
                continue;
            }
            if (strpos($property->doc, '@orm') !== false && strpos($property->doc, 'many') !== false) {
                continue;
            }
            $this->columnType[$property->name] = $property->getTypePatternByDoc();
            if ($property->value) {
                $query[self::TYPES][$property->name]      = $this->columnType[$property->name];
                $query[self::PARAMETERS][$property->name] = $property->value;
                $query[self::COLUMNS][$property->name]    = $property->name;
            }
        }
        $this->query = $query;

        return $this->$operation();
    }

    public function select()
    {
        $searchParams
            = empty($this->query[self::PARAMETERS][CoreRepository::SEARCH_PARAMS_DYNAMIC_PROPERTY])
            ? []
            : $this->query[self::PARAMETERS][CoreRepository::SEARCH_PARAMS_DYNAMIC_PROPERTY];

        $this->query[self::PARAMETERS] = [];
        $this->query[self::TYPES]      = [];
        $this->query[self::COLUMNS]    = [];

        $searchSql = new SearchSql($searchParams);
        $sql       = $this->getSqlQuery($searchSql);
        $pattern   = implode('', $this->query[self::TYPES]);
        $params    = array_filter($this->query[self::PARAMETERS]);
        $result    = [&$sql, &$pattern, &$params];

        return $result;
    }

    /**
     * @param SearchSql $searchSql
     *
     * @return string
     */
    private function getSqlQuery(SearchSql $searchSql)
    {
        $distinct = $searchSql->distinct ? 'DISTINCT' : '';
        $sql      = 'SELECT ' . $distinct . " * FROM `{$this->query[self::TABLE]}`";
        if (!empty($searchSql->join)) {
            $sql .= ' ' . $this->getQueryJoin($searchSql) . ' ';
        }
        $sql .= ' WHERE ';
        $sql .= !$searchSql->where ? 1 : $this->getQueryWhere($searchSql->where);
        if ($searchSql->groupby) {
            $sql .= ' GROUP BY ' . $searchSql->groupby;
        }
        if ($searchSql->having) {
            $sql .= ' HAVING ' . $searchSql->having;
        }
        if ($searchSql->orderby) {
            $searchSql->orderby = str_replace('`', '', $searchSql->orderby);
            $orders             = array_values(array_filter(explode(',', $searchSql->orderby)));
            $sql .= ' ORDER BY ';
            foreach ($orders as $order) {
                $orderBy  = array_values(array_filter(explode(' ', $order)));
                $orderSql = strpos($orderBy[0], '.') === false ? "`{$orderBy[0]}`" : $orderBy[0];
                $sql .= $orderSql . ' ';
                if (!empty($orderBy[1])) {
                    $sql .= $orderBy[1] . ' ';
                }
                $sql .= ',';
            }
            $sql = substr($sql, 0, -1);
        }
        if ($searchSql->limit) {
            $sql .= ' LIMIT ';
            $sql .= $searchSql->offset ? (int)$searchSql->offset . ', ' . (int)$searchSql->limit : $searchSql->limit;
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
        foreach ($searchSql->join as $joinName => $joinItem) {
            foreach ($joinItem as $joinItemValue) {
                $join .= " {$joinName} ";
                if (is_string($joinItemValue)) {
                    $join .= " {$joinItemValue} ";
                    continue;
                }
                if (is_array($joinItemValue)) {
                    $table = $on = '';
                    foreach ($joinItemValue as $joinItemValueKey => $joinItemValueString) {
                        if (strtolower($joinItemValueKey) == 'table') {
                            $table = $joinItemValueString;
                        } elseif (strtolower($joinItemValueKey) == 'on') {
                            $on = $joinItemValueString;
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
     * @param array $searchParams
     *
     * @return string
     */
    private function getQueryWhere(array $searchParams)
    {
        $sql = '';
        foreach ($searchParams as $operator => $params) {
            if (is_array($params) && count($params) > 1) {
                $sql .= ' ( ';
                $i = 0;
                foreach ($params as $key => $param) {
                    ++$i;
                    if (is_array($param)) {
                        $sql .= $this->getQueryWhere($param);
                    } else {
                        $sql .= $this->getPartOfCondition($key, $param);
                    }
                    if ($i < count($params)) {
                        $sql .= ' ' . $operator . ' ';
                    }
                }
                $sql .= ' ) ';
                continue;
            }
            if (is_array($params) && count($params) == 1) {
                $sql .= $this->getQueryWhere($params);
                continue;
            }

            $sql .= $this->getPartOfCondition($operator, $params);
        }

        return $sql;
    }

    /**
     * @param string $columnNameWithCompareOperator
     * @param string $param
     *
     * @return string
     */
    private function getPartOfCondition($columnNameWithCompareOperator, $param)
    {
        $conditionParts = $this->getColumnNameAndCompareOperation($columnNameWithCompareOperator);

        $column = str_replace('`', '', $conditionParts['columnName']);
        $column = strpos($column, '.') === false ? "`{$column}`" : $column;
        if (in_array(strtolower($conditionParts['compareOperator']), ['in', 'is', 'is not'])) {
            return " $column {$conditionParts['compareOperator']} {$param} ";
        }

        $this->query[self::PARAMETERS][] = $param;
        $this->query[self::TYPES][]      =
            empty($this->columnType[$conditionParts['columnName']])
                ? 's' : $this->columnType[$conditionParts['columnName']];

        return " $column {$conditionParts['compareOperator']} ? ";
    }

    /**
     * @param string $param
     *
     * @return array
     */
    private function getColumnNameAndCompareOperation($param)
    {
        $params     = explode(' ', $param);
        $columnName = empty($params[0]) ? '' : $params[0];
        unset($params[0]);
        $operator = empty($params[1]) ? '=' : implode(' ', $params);

        return [
            'columnName'      => trim($columnName),
            'compareOperator' => trim($operator)
        ];
    }

    public function insert()
    {
        $columns    = implode(',', $this->query[self::COLUMNS]);
        $paramsBind = $this->getParamsBindByQuery();

        $sql     = "INSERT INTO {$this->query[self::TABLE]} ({$columns}) VALUES ({$paramsBind})";
        $pattern = implode('', $this->query[self::TYPES]);
        $params  = array_filter($this->query[self::PARAMETERS]);
        $result  = [&$sql, &$pattern, &$params];

        return $result;
    }

    public function update()
    {
        $columns = implode('= ? ,', $this->query[self::COLUMNS]);
        if (!empty($columns)) {
            $columns .= ' = ?';
        }
        $id      = empty($this->query[self::PARAMETERS]['id']) ? 0 : $this->query[self::PARAMETERS]['id'];
        $sql     = "UPDATE {$this->query[self::TABLE]} SET {$columns} WHERE id = {$id} ;";
        $pattern = implode('', $this->query[self::TYPES]);
        $params  = array_filter($this->query[self::PARAMETERS]);
        $result  = [&$sql, &$pattern, &$params];

        return $result;
    }

    public function delete()
    {
        $where = '';
        $i     = 0;
        foreach ($this->query[self::COLUMNS] as $column) {
            $i++;
            $where .= " {$column} = ? ";
            if ($i != count($this->query[self::COLUMNS])) {
                $where .= ' AND ';
            }
        }
        if (!$where) {
            $where = ' 1 = 2 ';
        }

        $sql     = "DELETE FROM {$this->query[self::TABLE]} WHERE {$where}";
        $pattern = implode('', $this->query[self::TYPES]);
        $params  = array_filter($this->query[self::PARAMETERS]);
        $result  = [&$sql, &$pattern, &$params];

        return $result;
    }

    private function getParamsBindByQuery()
    {
        $params = [];
        $i      = 0;
        while ($i < count($this->query[self::COLUMNS])) {
            $params[] = "?";
            ++$i;
        }

        return implode(',', $params);
    }
}