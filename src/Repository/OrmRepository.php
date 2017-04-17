<?php

namespace AntOrm\Repository;

use AntOrm\Entity\Helpers\EntityPreparer;
use AntOrm\Entity\OrmEntity;
use AntOrm\Storage\OrmStorage;

class OrmRepository
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

        return $this->exec('select', $entity);
    }

    /**
     * @param OrmEntity $entity
     * @param bool      $asTransaction
     *
     * @return bool
     */
    public function insert($entity, $asTransaction = true)
    {
        return $this->make('insert', $entity, $asTransaction);
    }

    /**
     * @param OrmEntity $entity
     * @param bool      $asTransaction
     *
     * @return bool
     */
    public function update($entity, $asTransaction = true)
    {
        return $this->make('update', $entity, $asTransaction);
    }

    /**
     * @param OrmEntity $entity
     * @param bool      $asTransaction
     *
     * @return bool
     */
    public function delete($entity, $asTransaction = true)
    {
        return $this->make('delete', $entity, $asTransaction);
    }

    /**
     * @param           $operation
     * @param OrmEntity $entity
     * @param           $isTransaction
     *
     * @return bool
     */
    protected function make($operation, OrmEntity $entity, $isTransaction)
    {
        if ($this->storage->onTransaction()) {
            return $this->exec($operation, $entity);
        }
        if ($isTransaction && !$this->startTransaction()) {
            return false;
        }
        $result = $this->exec($operation, $entity);
        if ($isTransaction && !$result = $this->endTransaction()) {
            return false;
        }

        return $result;
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
     * @return array[]
     */
    public function result()
    {
        return $this->storage->getAdapter()->result();
    }

    /**
     * @return array[]
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
    protected function exec($operation, OrmEntity $entity)
    {
        $wrapper = EntityPreparer::getWrapper($entity);
        return $this->storage->make($operation, $wrapper);
    }
}