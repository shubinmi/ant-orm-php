<?php

namespace AntOrm\Entity\Objects;

use AntOrm\Common\Libraries\Hydrators\ConstructFromArrayOrJson;
use AntOrm\Entity\OrmEntity;

class OrmRelation extends ConstructFromArrayOrJson
{
    /**
     * @var OrmEntity
     */
    protected $with;

    /**
     * @var string [hasOne, hasMany]
     */
    protected $as;

    /**
     * @var string
     */
    protected $onMyColumn;

    /**
     * @var string
     */
    protected $onHisColumn;

    /**
     * @var OrmRelationMediator
     */
    protected $by;

    /**
     * @return OrmEntity
     */
    public function getWith()
    {
        return $this->with;
    }

    /**
     * @param string $with
     *
     * @return $this
     */
    public function setWith($with)
    {
        $declaredClasses = get_declared_classes();
        $myClass         = strtolower(trim(end(explode('\\', $with))));
        foreach ($declaredClasses as $declaredClass) {
            $class = explode('\\', $declaredClass);
            if (strtolower(trim(end($class))) == $myClass) {
                if (is_subclass_of($declaredClass, OrmEntity::class)) {
                    $class      = '\\' . $declaredClass;
                    $this->with = new $class();
                }
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function hasMany()
    {
        return $this->as === 'hasmany';
    }

    public function hasOne()
    {
        return $this->as === 'hasone';
    }

    /**
     * @param string $as
     *
     * @return $this
     */
    public function setAs($as)
    {
        $this->as = strtolower(str_replace(['-', '_', ' '], '', $as));
        return $this;
    }

    /**
     * @return string
     */
    public function getOnMyColumn()
    {
        return $this->onMyColumn;
    }

    /**
     * @param string $onMyColumn
     *
     * @return $this
     */
    public function setOnMyColumn($onMyColumn)
    {
        $this->onMyColumn = $onMyColumn;
        return $this;
    }

    /**
     * @return string
     */
    public function getOnHisColumn()
    {
        return $this->onHisColumn;
    }

    /**
     * @param string $onHisColumn
     *
     * @return $this
     */
    public function setOnHisColumn($onHisColumn)
    {
        $this->onHisColumn = $onHisColumn;
        return $this;
    }

    /**
     * @return OrmRelationMediator
     */
    public function getBy()
    {
        return $this->by;
    }

    /**
     * @param array|OrmRelationMediator $by
     *
     * @return $this
     */
    public function setBy($by)
    {
        if (is_array($by)) {
            $this->by = new OrmRelationMediator($by);
        } elseif ($by instanceof OrmRelationMediator) {
            $this->by = $by;
        }

        return $this;
    }
}