<?php

namespace AntOrm\Entity;

class EntityWrapper
{
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
}