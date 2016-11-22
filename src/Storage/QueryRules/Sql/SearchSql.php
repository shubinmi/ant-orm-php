<?php

namespace AntOrm\Storage\QueryRules\Sql;

class SearchSql
{
    /**
     * @var bool
     */
    public $distinct = false;

    /**
     * @var array
     */
    public $join = [];

    /**
     * @var array
     */
    public $where;

    /**
     * @var string
     */
    public $groupby;

    /**
     * @var string
     */
    public $having;

    /**
     * @var string
     */
    public $orderby;

    /**
     * @var string
     */
    public $limit;

    /**
     * @var string
     */
    public $offset;

    /**
     * @param array|null $params
     */
    public function __construct($params)
    {
        if (is_array($params)) {
            foreach ($params as $property => $value) {
                if ($this->checkOnJoin($property, $value)) {
                    unset($params[$property]);
                    continue;
                }
                $searchProperty = str_replace([' ', '-', '_'], '', $property);
                $searchProperty = strtolower($searchProperty);
                if (property_exists($this, $searchProperty)) {
                    $this->{$searchProperty} = $value;
                    unset($params[$property]);
                }
            }
            if (!$this->where && !empty($params)) {
                $this->where = $params;
            }
        }
    }

    /**
     * @param string $key
     * @param array $value
     *
     * @return bool
     */
    public function checkOnJoin($key, $value)
    {
        if (strripos($key, 'join') === false) {
            return false;
        }
        $this->join[$key] = $value;

        return true;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        $properties = get_object_vars($this);
        foreach ($properties as $property) {
            if (!empty($this->{$property})) {
                return false;
            }
        }

        return true;
    }
}