<?php

namespace AntOrm\Entity;

use AntOrm\Entity\Helpers\OrmMetaConstructor;
use AntOrm\Entity\Objects\OrmProperty;
use AntOrm\Entity\Objects\OrmTable;

class EntityMetaData
{
    /**
     * @var OrmTable
     */
    protected $table;

    /**
     * @var OrmProperty[]
     */
    protected $columns;

    /**
     * @return OrmTable
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param OrmTable $table
     *
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return OrmProperty[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @param OrmProperty[] $columns
     *
     * @return $this
     */
    public function setColumns(array $columns)
    {
        foreach ($columns as &$column) {
            if (!$this->table) {
                break;
            }
            OrmMetaConstructor::setRelationColumns($column, $this->table);
        }
        $this->columns = $columns;
        return $this;
    }

    /**
     * @param OrmProperty $column
     *
     * @return $this
     */
    public function addColumn(OrmProperty $column)
    {
        $this->columns[] = $column;
        return $this;
    }

    /**
     * @param string $propertyName
     *
     * @return OrmProperty|null
     */
    public function getColumn($propertyName)
    {
        return empty($this->columns[$propertyName]) ? null : $this->columns[$propertyName];
    }

    /**
     * @param string      $propertyName
     * @param OrmProperty $column
     *
     * @return $this
     */
    public function setColumn($propertyName, OrmProperty $column)
    {
        $this->columns[$propertyName] = $column;
        return $this;
    }
}