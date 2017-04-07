<?php

namespace AntOrm\QueryRules;

class TransactionQueryList
{
    /**
     * @var QueryStructure[]
     */
    private $queries = [];

    /**
     * @return QueryStructure[]
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * @param QueryStructure[] $queries
     *
     * @return $this
     */
    public function setQueries($queries)
    {
        $this->queries = $queries;
        return $this;
    }

    /**
     * @param QueryStructure $query
     *
     * @return $this
     */
    public function addQuery(QueryStructure $query)
    {
        $this->queries[] = $query;
        return $this;
    }
}