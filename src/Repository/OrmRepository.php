<?php

namespace AntOrm\Repository;

use AntOrm\Entity\Helpers\EntityPreparer;
use AntOrm\Entity\OrmEntity;
use AntOrm\Storage\OrmStorage;

class OrmRepository
{
    const OPERATION_SELECT = 'select';
    const OPERATION_INSERT = 'insert';
    const OPERATION_UPDATE = 'update';
    const OPERATION_DELETE = 'delete';

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

        return $this->exec(self::OPERATION_SELECT, $entity);
    }

    /**
     * @param OrmEntity $entity
     * @param bool      $asTransaction
     *
     * @return bool
     */
    public function insert(OrmEntity $entity, $asTransaction = true)
    {
        $entity->beforeInsert();
        $wasSuccess = $this->make(self::OPERATION_INSERT, $entity, $asTransaction);
        $entity->afterInsert($wasSuccess);

        return $wasSuccess;
    }

    /**
     * @param OrmEntity $entity
     * @param bool      $asTransaction
     *
     * @return bool
     */
    public function update(OrmEntity $entity, $asTransaction = true)
    {
        $entity->beforeUpdate();
        $wasSuccess = $this->make(self::OPERATION_UPDATE, $entity, $asTransaction);
        $entity->afterUpdate($wasSuccess);

        return $wasSuccess;
    }

    /**
     * @param OrmEntity $entity
     * @param bool      $asTransaction
     *
     * @return bool
     */
    public function delete(OrmEntity $entity, $asTransaction = true)
    {
        $entity->beforeDelete();
        $wasSuccess = $this->make(self::OPERATION_DELETE, $entity, $asTransaction);
        $entity->afterDelete($wasSuccess);

        return $wasSuccess;
    }

    /**
     * @param string    $operation
     * @param OrmEntity $entity
     * @param bool      $asTransaction
     */
    public function beforeMake($operation, OrmEntity $entity, $asTransaction)
    {
    }

    /**
     * @param string    $operation
     * @param OrmEntity $entity
     * @param bool      $asTransaction
     * @param bool      $wasSuccess
     */
    public function afterMake($operation, OrmEntity $entity, $asTransaction, $wasSuccess)
    {
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
        $this->beforeMake($operation, $entity, $isTransaction);
        if ($this->storage->onTransaction()) {
            $result = $this->exec($operation, $entity);
            $this->afterMake($operation, $entity, $isTransaction, $result);
            return $result;
        }
        if ($isTransaction && !$this->startTransaction()) {
            $this->afterMake($operation, $entity, $isTransaction, false);
            return false;
        }
        $result = $this->exec($operation, $entity);
        if ($isTransaction && !$result = $this->endTransaction()) {
            $this->afterMake($operation, $entity, $isTransaction, false);
            return false;
        }
        $this->afterMake($operation, $entity, $isTransaction, $result);

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