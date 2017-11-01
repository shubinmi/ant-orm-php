<?php

namespace AntOrm\QueryRules\Sql;

use AntOrm\Entity\EntityProperty;
use AntOrm\Entity\EntityWrapper;
use AntOrm\Entity\Helpers\WrappersLinking;
use AntOrm\QueryRules\CrudDbInterface;
use AntOrm\QueryRules\Helpers\ColumnNameParser;
use AntOrm\QueryRules\Helpers\SelectSqlFromWrapper;
use AntOrm\QueryRules\QueryStructure;
use AntOrm\QueryRules\TransactionQueryList;

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
    public function upsert(EntityWrapper $wrapper)
    {
        return $this->extendedInsert($wrapper, true);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return QueryStructure
     */
    public function insert(EntityWrapper $wrapper)
    {
        return $this->extendedInsert($wrapper);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return QueryStructure
     */
    public function update(EntityWrapper $wrapper)
    {
        $mediatorQuery = null;
        if ($wrapper->isMeToParentAsIHaveMany()) {
            return $this->extendedInsert($wrapper, true);
        } elseif ($wrapper->isMeToParentAsIHaveOne()) {
            $mediatorQuery = $this->extendedInsert($wrapper, true);
        }
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
        if ($mediatorQuery) {
            if ($mediatorQuery instanceof TransactionQueryList) {
                $query = $mediatorQuery->addQuery($query);
            } else {
                $query = (new TransactionQueryList())->addQuery($mediatorQuery)->addQuery($query);
            }
        }
        return $query;
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return QueryStructure
     */
    public function delete(EntityWrapper $wrapper)
    {
        if (
            $wrapper->isMeToParentAsIHaveMany()
            && $parentProperty = $this->findParentProperty($wrapper)
        ) {
            return $this->getUnlinkMeQueryForDelete($wrapper, $parentProperty);
        }
        $query                  = new QueryStructure();
        $properties             = $wrapper->getPreparedProperties();
        $primaryColumns         = $wrapper->getMetaData()->getTable()->getPrimaryProperties();
        $declaredPrimaryColumns = !empty($primaryColumns);
        $where                  = '';
        /** @var QueryStructure[] $relatedQueries */
        $relatedQueries = [];
        /** @var EntityProperty $property */
        foreach ($properties as $property) {
            if ($property->parentEntityWrapper) {
                $hasConnect = false;
                foreach ($property->parentEntityWrapper->getPreparedProperties() as $parentProperty) {
                    if (!$parentProperty->metaData->getRelated()) {
                        continue;
                    }
                    if (
                        get_class($parentProperty->metaData->getRelated()->getWith())
                        == get_class($wrapper->getEntity())
                    ) {
                        $columnForValue = $parentProperty->metaData->getRelated()->getOnMyColumn();
                        foreach ($property->parentEntityWrapper->getPreparedProperties() as $searchValueProperty) {
                            if ($columnForValue == $searchValueProperty->metaData->getColumn()) {
                                $val  = $searchValueProperty->value;
                                $bind = $searchValueProperty->getBindTypePattern();
                                break;
                            }
                        }
                        $hasConnect = true;
                        break;
                    }
                }
                if ($hasConnect && isset($val) && isset($bind)) {
                    $query = new QueryStructure();
                    $where = "`{$property->metaData->getColumn()}` = ?";
                    $query->addBindParam($val);
                    $query->addBindPattern($bind);
                    break;
                }
            }
            if (!isset($property->value) || $property->metaData->getRelated()) {
                continue;
            }
            if (!$declaredPrimaryColumns && empty($where)) {
                if (strpos($property->metaData->getColumn(), 'id') !== false) {
                    $where .= "`{$property->metaData->getColumn()}` = ?";
                    $query->addBindParam($property->value);
                    $query->addBindPattern($property->getBindTypePattern());
                }
                continue;
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
        if (!empty($relatedQueries)) {
            $query = (new TransactionQueryList())->setQueries($relatedQueries)->addQuery($query);
        }

        return $query;
    }

    public function save(EntityWrapper $wrapper)
    {
        // Not used
    }

    public function unlink(EntityWrapper $wrapper)
    {
        return $this->unlinkMe($wrapper);
    }

    /**
     * @param EntityWrapper $myWrapper
     *
     * @return QueryStructure
     */
    public function unlinkMe(EntityWrapper $myWrapper)
    {
        if (!$myWrapper->isMeToParentAsIHaveMany()) {
            return null;
        }
        if (!$parentProperty = $this->findParentProperty($myWrapper)) {
            return null;
        }
        $mySelectColumn    = $parentProperty->metaData->getRelated()->getOnHisColumn();
        $hisSelectColumn   = $parentProperty->metaData->getRelated()->getOnMyColumn();
        $mediatorTable     = $parentProperty->metaData->getRelated()->getBy()->getTable();
        $mediatorMyColumn  = $parentProperty->metaData->getRelated()->getBy()->getRelatedColumn();
        $mediatorHisColumn = $parentProperty->metaData->getRelated()->getBy()->getMyColumn();
        $queryMediator     = new QueryStructure();
        foreach ($myWrapper->getPreparedProperties() as $property) {
            if (!isset($property->value)) {
                continue;
            }
            if ($property->metaData->getColumn() == $mySelectColumn) {
                $queryMediator->addBindParam($property->value);
                $queryMediator->addBindPattern($property->getBindTypePattern());
                break;
            }
        }
        foreach ($myWrapper->getMyParent()->getPreparedProperties() as $property) {
            if (!isset($property->value)) {
                continue;
            }
            if ($property->metaData->getColumn() == $hisSelectColumn) {
                $queryMediator->addBindParam($property->value);
                $queryMediator->addBindPattern($property->getBindTypePattern());
                break;
            }
        }
        if (count($queryMediator->getBindParams()) != 2) {
            return new QueryStructure();
        }
        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlDialectInspection */
        $queryMediator->setQuery(
            "DELETE FROM `{$mediatorTable}` WHERE `{$mediatorMyColumn}` = ? AND `{$mediatorHisColumn}` = ?"
        );

        return $queryMediator;
    }

    /**
     * @param EntityWrapper $myWrapper
     *
     * @return QueryStructure
     */
    private function linkMe(EntityWrapper $myWrapper)
    {
        if (!$myWrapper->isMeToParentAsIHaveMany()) {
            return null;
        }
        if (!$parentProperty = $this->findParentProperty($myWrapper)) {
            return null;
        }
        $myTable           = $myWrapper->getMetaData()->getTable()->getName();
        $hisTable          = $myWrapper->getMyParent()->getMetaData()->getTable()->getName();
        $mySelectColumn    = $parentProperty->metaData->getRelated()->getOnHisColumn();
        $hisSelectColumn   = $parentProperty->metaData->getRelated()->getOnMyColumn();
        $mediatorTable     = $parentProperty->metaData->getRelated()->getBy()->getTable();
        $mediatorMyColumn  = $parentProperty->metaData->getRelated()->getBy()->getRelatedColumn();
        $mediatorHisColumn = $parentProperty->metaData->getRelated()->getBy()->getMyColumn();

        $queryMediator = new QueryStructure();
        $where         = '';
        foreach ($myWrapper->getPreparedProperties() as $property) {
            if (!isset($property->value) || $property->metaData->getRelated()) {
                continue;
            }
            $queryMediator->addBindParam($property->value);
            $queryMediator->addBindPattern($property->getBindTypePattern());
            if ($where) {
                $where .= ' AND ';
            }
            $where .= " `{$property->metaData->getColumn()}` = ? ";
        }
        /** @noinspection SqlDialectInspection */
        /** @noinspection SqlNoDataSourceInspection */
        $sqlMyColumn =
            "SELECT `{$mySelectColumn}` FROM `{$myTable}` WHERE {$where} ORDER BY `{$mySelectColumn}` DESC LIMIT 1";

        $where = '';
        foreach ($myWrapper->getMyParent()->getPreparedProperties() as $property) {
            if (!isset($property->value) || $property->metaData->getRelated()) {
                continue;
            }
            $queryMediator->addBindParam($property->value);
            $queryMediator->addBindPattern($property->getBindTypePattern());
            if ($where) {
                $where .= ' AND ';
            }
            $where .= " `{$property->metaData->getColumn()}` = ? ";
        }
        /** @noinspection SqlDialectInspection */
        /** @noinspection SqlNoDataSourceInspection */
        $sqlHisColumn =
            "SELECT `{$hisSelectColumn}` FROM `{$hisTable}` WHERE {$where} ORDER BY `{$hisSelectColumn}` DESC LIMIT 1";
        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlDialectInspection */
        $queryMediator->setQuery(
            "INSERT IGNORE INTO `{$mediatorTable}` (`{$mediatorMyColumn}`, `{$mediatorHisColumn}`) VALUES (({$sqlMyColumn}), ({$sqlHisColumn}))"
        );

        return $queryMediator;
    }

    /**
     * @param EntityWrapper $myWrapper
     *
     * @return EntityProperty|null
     */
    private function findParentProperty(EntityWrapper $myWrapper)
    {
        if (!$parentProperty = WrappersLinking::getRelatedParentPropertyForMany2Many($myWrapper)) {
            return null;
        }
        if (!$parentProperty->metaData->getRelated() || !$parentProperty->metaData->getRelated()->getBy()) {
            return null;
        }
        return $parentProperty;
    }

    /**
     * @param EntityProperty $hisEntityProperty
     * @param EntityWrapper  $myEntityWrapper
     *
     * @return QueryStructure|null
     */
    private function getLinkedColumnForInsert(
        EntityProperty $hisEntityProperty, EntityWrapper $myEntityWrapper
    ) {
        $hisProperties   = $hisEntityProperty->parentEntityWrapper->getPreparedProperties();
        $hisTable        = $hisEntityProperty->parentEntityWrapper->getMetaData()->getTable()->getName();
        $queryRootColumn = new QueryStructure();
        $hisSelectColumn = '';
        $where           = '';
        $className       = get_class($myEntityWrapper->getEntity());
        /** @var EntityProperty $property */
        foreach ($hisProperties as $property) {
            if (
                empty($hisSelectColumn)
                && $property->metaData->getRelated()
                && $property->metaData->getRelated()->getWith() instanceof $className
            ) {
                $hisPropertyRelated = $property->metaData->getRelated();
                $hisSelectColumn    = $hisPropertyRelated->getOnMyColumn();
            }
            if (!isset($property->value) || $property->metaData->getRelated()) {
                continue;
            }
            $queryRootColumn->addBindParam($property->value);
            $queryRootColumn->addBindPattern($property->getBindTypePattern());
            if ($where) {
                $where .= ' AND ';
            }
            $where .= " `{$property->metaData->getColumn()}` = ? ";
        }
        if (empty($hisSelectColumn) || empty($where)) {
            return null;
        }
        /** @noinspection SqlDialectInspection */
        /** @noinspection SqlNoDataSourceInspection */
        $queryRootColumn->setQuery(
            "SELECT `{$hisSelectColumn}` FROM `{$hisTable}` WHERE {$where} ORDER BY `{$hisSelectColumn}` DESC LIMIT 1"
        );

        return $queryRootColumn;
    }

    /**
     * @param EntityWrapper $wrapper
     * @param bool          $ignoreDuplicate
     *
     * @return QueryStructure|TransactionQueryList
     */
    private function extendedInsert(EntityWrapper $wrapper, $ignoreDuplicate = false)
    {
        $query       = new QueryStructure();
        $properties  = $wrapper->getPreparedProperties();
        $queryValues = $queryColumns = [];
        $mediators   = [];
        if ($wrapper->isMeToParentAsIHaveMany()) {
            $mediators[]     = $this->unlinkMe($wrapper);
            $mediators[]     = $this->linkMe($wrapper);
            $ignoreDuplicate = true;
        }
        /** @var EntityProperty $property */
        foreach ($properties as $property) {
            if (
                (!isset($property->value) && !$property->parentEntityWrapper)
                || $property->metaData->getRelated()
            ) {
                continue;
            }
            if (!$property->parentEntityWrapper) {
                $query->addBindParam($property->value);
                $query->addBindPattern($property->getBindTypePattern());
                $queryValues[]  = '?';
                $queryColumns[] = $property->metaData->getColumn();
                continue;
            }
            if (!$queryForColumn = $this->getLinkedColumnForInsert($property, $wrapper)) {
                continue;
            }
            $query->setBindParams(
                array_merge(
                    $query->getBindParams(), $queryForColumn->getBindParams()
                )
            );
            $query->setBindPatterns(
                array_merge(
                    $query->getBindPatterns(), $queryForColumn->getBindPatterns()
                )
            );
            $queryValues[]  = "( {$queryForColumn->getQuery()} )";
            $queryColumns[] = $property->metaData->getColumn();
        }
        $queryColumns = implode('`,`', $queryColumns);
        $queryValues  = implode(' , ', $queryValues);
        if (!empty($queryColumns)) {
            $queryColumns = '`' . $queryColumns . '`';
        }
        $tableName = $wrapper->getMetaData()->getTable()->getName();
        $ignore    = '';
        if ($ignoreDuplicate) {
            $ignore = 'IGNORE';
        }
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "INSERT {$ignore} INTO `{$tableName}` ({$queryColumns}) VALUES ({$queryValues})";
        $query->setQuery($sql);
        if (empty($mediators)) {
            return $query;
        }
        $transaction = new TransactionQueryList();
        $query       = $transaction->addQuery($query);
        foreach ($mediators as $mediator) {
            $query->addQuery($mediator);
        }
        return $query;
    }

    /**
     * For many2many
     *
     * @param EntityWrapper  $wrapper
     * @param EntityProperty $parentProperty
     *
     * @return QueryStructure
     */
    private function getUnlinkMeQueryForDelete(EntityWrapper $wrapper, EntityProperty $parentProperty)
    {
        $hisTable          = $wrapper->getMyParent()->getMetaData()->getTable()->getName();
        $hisSelectColumn   = $parentProperty->metaData->getRelated()->getOnMyColumn();
        $mediatorTable     = $parentProperty->metaData->getRelated()->getBy()->getTable();
        $mediatorHisColumn = $parentProperty->metaData->getRelated()->getBy()->getMyColumn();
        $query             = new QueryStructure();
        $where             = '';
        $parentPrimProps   = !empty($wrapper->getMyParent()->getMetaData()->getTable()->getPrimaryProperties());
        foreach ($wrapper->getMyParent()->getPreparedProperties() as $property) {
            if ($parentPrimProps && !$property->metaData->isPrimary()) {
                continue;
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
        if (empty($where)) {
            return new QueryStructure();
        }
        /** @noinspection SqlDialectInspection */
        /** @noinspection SqlNoDataSourceInspection */
        $sqlHisColumn =
            "SELECT `{$hisSelectColumn}` FROM `{$hisTable}` WHERE {$where} ORDER BY `{$hisSelectColumn}` DESC LIMIT 1";

        /** @noinspection SqlDialectInspection */
        /** @noinspection SqlNoDataSourceInspection */
        $query->setQuery(
            "DELETE FROM `{$mediatorTable}` WHERE `{$mediatorHisColumn}` = ({$sqlHisColumn})"
        );

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
        $sql .= !$searchSql->where
            ? 1
            : $this->getQueryWhere(
                $selectParts, $wrapper, $searchSql->where, $queryStructure
            );
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
     * @param RelatedSelectSqlParts $selectParts
     * @param EntityWrapper         $wrapper
     * @param array                 $whereParams
     * @param QueryStructure        $queryStructure
     * @param string                $logicOperator
     *
     * @return string
     */
    private function getQueryWhere(
        RelatedSelectSqlParts $selectParts, EntityWrapper $wrapper,
        array $whereParams, QueryStructure &$queryStructure, $logicOperator = 'AND'
    ) {
        $sql              = '';
        $iterateNumber    = 0;
        $countWhereParams = count($whereParams);
        foreach ($whereParams as $key => $param) {
            $iterateNumber++;
            if (in_array(strtolower($key), ['or', 'and']) && is_array($param)) {
                $sql .= ' ( ' . $this->getQueryWhere($selectParts, $wrapper, $param, $queryStructure, $key) . ' ) ';
            } elseif (is_array($param)) {
                $sql .= ' ( ' . $this->getQueryWhere($selectParts, $wrapper, $param, $queryStructure, $logicOperator)
                    . ' ) ';
            } else {
                $sql .= $this->getPartOfCondition($selectParts, $key, $param, $queryStructure, $wrapper);
            }
            if ($iterateNumber < $countWhereParams) {
                $sql .= ' ' . $logicOperator . ' ';
            }
        }

        return $sql;
    }

    /**
     * @param RelatedSelectSqlParts $selectParts
     * @param string                $columnNameWithCompareOperator
     * @param string                $columnValue
     * @param QueryStructure        $queryStructure
     *
     * @param EntityWrapper         $wrapper
     *
     * @return string
     */
    private function getPartOfCondition(
        RelatedSelectSqlParts $selectParts, $columnNameWithCompareOperator,
        $columnValue, QueryStructure &$queryStructure, EntityWrapper $wrapper
    ) {
        if (!$conditionParts = $this->getColumnNameAndCompareOperation($columnNameWithCompareOperator)) {
            return '';
        }
        $columnName          = $conditionParts['columnName'];
        $operator            = $conditionParts['compareOperator'];
        $escapedColumnName   = ColumnNameParser::getEscaped(
            $columnName, $wrapper->getMetaData()->getTable()->getName(), $selectParts
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
        $operator   = empty($params[1]) ? '=' : implode(' ', $params);
        $columnName = str_replace('`', '', $columnName);

        return [
            'columnName'      => trim($columnName),
            'compareOperator' => strtolower(trim($operator))
        ];
    }
}