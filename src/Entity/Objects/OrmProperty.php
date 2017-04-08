<?php

namespace AntOrm\Entity\Objects;

use AntOrm\Common\Libraries\Hydrators\ConstructFromArrayOrJson;

class OrmProperty extends ConstructFromArrayOrJson
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $column;

    /**
     * @var OrmRelation
     */
    protected $related;

    /**
     * @var bool
     */
    protected $primary = false;

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @param string $column
     *
     * @return $this
     */
    public function setColumn($column)
    {
        $this->column = $column;
        return $this;
    }

    /**
     * @return bool
     */
    public function isPrimary()
    {
        return $this->primary;
    }

    /**
     * @param bool $primary
     *
     * @return $this
     */
    public function setPrimary($primary)
    {
        $this->primary = $primary;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return OrmRelation
     */
    public function getRelated()
    {
        return $this->related;
    }

    /**
     * @param array|OrmRelation $related
     *
     * @return $this
     */
    public function setRelated($related)
    {
        if (is_array($related)) {
            $this->related = new OrmRelation($related);
        } elseif ($related instanceof OrmRelation) {
            $this->related = $related;
        }

        return $this;
    }
}