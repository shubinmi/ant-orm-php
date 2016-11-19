<?php

namespace AntOrm\Repository;

use AntOrm\Entity\CoreEntity;
use AntOrm\Entity\EntityProperty;
use AntOrm\Storage\CoreStorage;

class CoreRepository implements RepositoryInterface
{
    const SEARCH_PARAMS_DYNAMIC_PROPERTY = 'antOrmSearchParams';

    /**
     * @var CoreStorage
     */
    protected $storage;

    /**
     * @var string
     */
    protected $entityClass;

    /**
     * @param CoreStorage $storage
     */
    public function __construct(CoreStorage $storage)
    {
        $this->storage = $storage;
        $this->init();
    }

    protected function init()
    {
    }

    /**
     * @param array $params
     *
     * @return CoreEntity[]
     * @throws \Exception
     */
    public function find(array $params = [])
    {
        if (!is_subclass_of($this->entityClass, CoreEntity::className())) {
            throw new \Exception('Property "entityClass" has to be a children of ' . CoreEntity::className());
        }
        self::select($this->entityClass, $params);
        $result = self::result();
        $items  = [];
        foreach ($result as $item) {
            $items[] = new $this->entityClass($item);
        }

        return $items;
    }

    /**
     * @param string $entityClass
     * @param array  $searchParams
     *
     * @return mixed
     */
    public function select($entityClass, $searchParams = [])
    {
        /** @var CoreEntity $entity */
        $entity                                         = new $entityClass();
        $entity->{self::SEARCH_PARAMS_DYNAMIC_PROPERTY} = $searchParams;

        return $this->query('select', $entity);
    }

    /**
     * @param CoreEntity $entity
     *
     * @return mixed
     * @throws \Exception
     */
    public function insert($entity)
    {
        return $this->query('insert', $entity);
    }

    public function update($entity)
    {
        return $this->query('update', $entity);
    }

    /**
     * @param CoreEntity $entity
     *
     * @return mixed
     * @throws \Exception
     */
    public function delete($entity)
    {
        return $this->query('delete', $entity);
    }

    public function result()
    {
        return $this->storage->getAdapter()->result();
    }

    public function lastResult()
    {
        return $this->storage->getAdapter()->getLastResult();
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->storage->getAdapter()->getLastResult());
    }

    /**
     * @return int
     */
    public function lastInsertId()
    {
        return $this->storage->getAdapter()->getLastInsertId();
    }

    /**
     * @return CoreStorage
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @param string     $operation
     * @param CoreEntity $entity
     *
     * @return mixed
     * @throws \Exception
     */
    protected function query($operation, CoreEntity $entity)
    {
        return $this->storage->query($operation, $this->getEntityProperty($entity));
    }

    /**
     * @param CoreEntity $entity
     *
     * @return EntityProperty[]
     */
    protected function getEntityProperty(CoreEntity $entity)
    {
        $reflect    = new \ReflectionClass($entity);
        $properties = $reflect->getProperties(
            \ReflectionProperty::IS_PUBLIC |
            \ReflectionProperty::IS_PROTECTED |
            \ReflectionProperty::IS_PRIVATE
        );

        $result = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $result[$property->getName()] =
                new EntityProperty($property->getName(), $property->getValue($entity), $property->getDocComment());
        }

        return $result;
    }
}