<?php

namespace AntOrm\Entity;

class EntityWrapper
{
    const I_HAVE_MANY = 'haveMany';
    const I_HAVE_ONE  = 'haveOne';

    /**
     * @var OrmEntity
     */
    protected $entity;

    /**
     * @var EntityMetaData
     */
    protected $metaData;

    /**
     * @var EntityProperty[]
     */
    protected $preparedProperties;

    /**
     * @var EntityWrapper
     */
    protected $myParent;

    /**
     * @var string
     */
    protected $relationshipWithParent;

    /**
     * @return OrmEntity
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @param OrmEntity $entity
     *
     * @return $this
     */
    public function setEntity(OrmEntity $entity)
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * @return EntityMetaData
     */
    public function getMetaData()
    {
        return $this->metaData;
    }

    /**
     * @param EntityMetaData $metaData
     *
     * @return $this
     */
    public function setMetaData(EntityMetaData $metaData)
    {
        $this->metaData = $metaData;
        return $this;
    }

    /**
     * @return EntityProperty[]
     */
    public function getPreparedProperties()
    {
        return $this->preparedProperties;
    }

    /**
     * @param EntityProperty[] $preparedProperties
     *
     * @return $this
     */
    public function setPreparedProperties(array $preparedProperties)
    {
        $this->preparedProperties = $preparedProperties;
        return $this;
    }

    /**
     * @return EntityWrapper
     */
    public function getMyParent()
    {
        return $this->myParent;
    }

    /**
     * @param EntityWrapper $myParent
     *
     * @return $this
     */
    public function setMyParent($myParent)
    {
        $this->myParent = $myParent;
        return $this;
    }

    /**
     * @return bool
     */
    public function isMeToParentAsIHaveMany()
    {
        return $this->relationshipWithParent == self::I_HAVE_MANY;
    }

    /**
     * @return bool
     */
    public function isMeToParentAsIHaveOne()
    {
        return $this->relationshipWithParent == self::I_HAVE_ONE;
    }

    /**
     * @return $this
     */
    public function setMeToParentAsIHaveMany()
    {
        $this->relationshipWithParent = self::I_HAVE_MANY;
        return $this;
    }

    /**
     * @return $this
     */
    public function setMeToParentAsIHaveOne()
    {
        $this->relationshipWithParent = self::I_HAVE_ONE;
        return $this;
    }
}