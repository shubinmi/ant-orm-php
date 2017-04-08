<?php

namespace AntOrm\Entity\Objects;

use AntOrm\Common\Libraries\Hydrators\ConstructFromArrayOrJson;

class OrmRelationMediator extends ConstructFromArrayOrJson
{
    /**
     * @var string table
     */
    protected $table;

    /**
     * @var string table column name
     */
    protected $myColumn;

    /**
     * @var string table column field
     */
    protected $hisColumn;

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param string $table
     *
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return string
     */
    public function getMyColumn()
    {
        return $this->myColumn;
    }

    /**
     * @param string $myColumn
     *
     * @return $this
     */
    public function setMyColumn($myColumn)
    {
        $this->myColumn = $myColumn;
        return $this;
    }

    /**
     * @return string
     */
    public function getHisColumn()
    {
        return $this->hisColumn;
    }

    /**
     * @param string $hisColumn
     *
     * @return $this
     */
    public function setHisColumn($hisColumn)
    {
        $this->hisColumn = $hisColumn;
        return $this;
    }
}