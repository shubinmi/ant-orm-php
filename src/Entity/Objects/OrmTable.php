<?php

namespace AntOrm\Entity\Objects;

use AntOrm\Common\Libraries\Hydrators\ConstructFromArrayOrJson;

class OrmTable extends ConstructFromArrayOrJson
{
    /**
     * @var string
     */
    protected $table;

    /**
     * @var array
     */
    protected $primaryProperties = [];

    /**
     * @return string
     */
    public function getName()
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
     * @return array
     */
    public function getPrimaryProperties()
    {
        return $this->primaryProperties;
    }

    /**
     * @param array $primaryProperties
     *
     * @return $this
     */
    public function setPrimaryProperties(array $primaryProperties)
    {
        $this->primaryProperties = $primaryProperties;
        return $this;
    }

    /**
     * @param string $primaryProperty
     *
     * @return $this
     */
    public function addPrimaryProperty($primaryProperty)
    {
        $this->primaryProperties[] = trim($primaryProperty);
        return $this;
    }
}