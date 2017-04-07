<?php

namespace AntOrm\Repository;

use AntOrm\Entity\OrmEntity;
use AntOrm\Entity\EntityMetaData;
use AntOrm\Entity\EntityWrapper;
use AntOrm\Entity\Helpers\EntityPrepareHelper;
use AntOrm\Storage\OrmStorage;

class OrmRepository implements RepositoryInterface
{
    /**
     * @var OrmStorage
     */
    protected $storage;

    /**
     * @var string
     */
    protected $entityClass;

    /**
     * @param OrmStorage  $storage
     * @param string|null $entityClass
     */
    public function __construct(OrmStorage $storage, $entityClass = null)
    {
        $this->storage = $storage;
        if (is_string($entityClass)) {
            $this->entityClass = $entityClass;
        }
        $this->init();
    }

    function __clone()
    {
        $this->storage = clone $this->storage;
    }

    protected function init()
    {
    }

    /**
     * @param array $params
     *
     * @return OrmEntity[]
     * @throws \Exception
     */
    public function find(array $params = [])
    {
        if (!is_subclass_of($this->entityClass, OrmEntity::className())) {
            throw new \Exception('Property "entityClass" has to be a children of ' . OrmEntity::className());
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
     * @return bool
     */
    public function select($entityClass, $searchParams = [])
    {
        /** @var OrmEntity $entity */
        $entity                     = new $entityClass();
        $entity->antOrmSearchParams = $searchParams;

        return $this->query('select', $entity);
    }

    /**
     * @param OrmEntity $entity
     *
     * @return bool
     * @throws \Exception
     */
    public function insert($entity)
    {
        return $this->query('insert', $entity);
    }

    /**
     * @param OrmEntity $entity
     *
     * @return bool
     */
    public function update($entity)
    {
        return $this->query('update', $entity);
    }

    /**
     * @param OrmEntity $entity
     *
     * @return bool
     * @throws \Exception
     */
    public function delete($entity)
    {
        return $this->query('delete', $entity);
    }

    /**
     * @return bool
     */
    public function startTransaction()
    {
        return $this->storage->startTransaction();
    }

    /**
     * @return bool
     */
    public function endTransaction()
    {
        return $this->storage->endTransactions();
    }

    /**
     * @return mixed
     */
    public function result()
    {
        return $this->storage->getAdapter()->result();
    }

    /**
     * @return mixed
     */
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
     * @return OrmStorage
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @param string    $operation
     * @param OrmEntity $entity
     *
     * @return bool
     * @throws \Exception
     */
    protected function query($operation, OrmEntity $entity)
    {
        $preparedProperties = EntityPrepareHelper::getEntityProperties($entity);
        $propertiesMetaData = EntityPrepareHelper::getPropertiesMeta($preparedProperties);
        $metaData           = new EntityMetaData();
        $metaData
            ->setTable(EntityPrepareHelper::getTableMeta($entity, $propertiesMetaData))
            ->setColumns($propertiesMetaData);
        $wrapper = new EntityWrapper();
        $wrapper->setEntity($entity)
            ->setPreparedProperties($preparedProperties)
            ->setMetaData($metaData);

        return $this->storage->query($operation, $wrapper);
    }
}