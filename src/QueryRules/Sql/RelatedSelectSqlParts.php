<?php

namespace AntOrm\QueryRules\Sql;

class RelatedSelectSqlParts
{
    /**
     * @var string[]
     */
    private $selects = [];

    /**
     * @var string[]
     */
    private $joins = [];

    /**
     * @return \string[]
     */
    public function getSelects()
    {
        return $this->selects;
    }

    /**
     * @param \string[] $selects
     *
     * @return $this
     */
    public function setSelects(array $selects)
    {
        $this->selects = $selects;
        return $this;
    }

    /**
     * @param string $select
     *
     * @return $this
     */
    public function addSelect($select)
    {
        $this->selects[] = $select;
        return $this;
    }

    /**
     * @return \string[]
     */
    public function getJoins()
    {
        return $this->joins;
    }

    /**
     * @param \string[] $joins
     *
     * @return $this
     */
    public function setJoins(array $joins)
    {
        $this->joins = $joins;
        return $this;
    }

    /**
     * @param string $join
     *
     * @return $this
     */
    public function addJoin($join)
    {
        $this->joins[] = $join;
        return $this;
    }
}