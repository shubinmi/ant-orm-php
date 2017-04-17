<?php

namespace AntOrm\QueryRules;

class QueryStructure
{
    /**
     * @var string
     */
    protected $query = '';

    /**
     * @var array
     */
    protected $bindPatterns = [];

    /**
     * @var array
     */
    protected $bindParams = [];

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string $query
     *
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @return array
     */
    public function getBindPatterns()
    {
        return $this->bindPatterns;
    }

    /**
     * @param array $bindPatterns
     *
     * @return $this
     */
    public function setBindPatterns(array $bindPatterns)
    {
        $this->bindPatterns = $bindPatterns;
        return $this;
    }

    /**
     * @param string $bindPattern
     *
     * @return $this
     */
    public function addBindPattern($bindPattern)
    {
        $this->bindPatterns[] = $bindPattern;
        return $this;
    }

    /**
     * @return array
     */
    public function getBindParams()
    {
        return $this->bindParams;
    }

    /**
     * @param array $bindParams
     *
     * @return $this
     */
    public function setBindParams(array $bindParams)
    {
        $this->bindParams = $bindParams;
        return $this;
    }

    /**
     * @param string $bindParam
     *
     * @return $this
     */
    public function addBindParam($bindParam)
    {
        $this->bindParams[] = $bindParam;
        return $this;
    }
}