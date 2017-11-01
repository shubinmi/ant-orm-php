<?php

namespace AntOrm\Repository;

use AntOrm\Entity\Helpers\EntityPreparer;
use AntOrm\Entity\Helpers\WrappersLinking;
use AntOrm\Entity\OrmEntity;
use AntOrm\QueryRules\CrudDbInterface;
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

    public function __clone()
    {
        $this->storage = clone $this->storage;
    }

    /**
     * @param array          $params
     * @param OrmEntity|null $entityClass
     *
     * @return OrmEntity[]
     * @throws \Exception
     */
    public function find(array $params = [], OrmEntity $entityClass = null)
    {
        if (!$entityClass) {
            $entityClass = $this->entityClass;
        }
        if (!is_subclass_of($entityClass, OrmEntity::className())) {
            throw new \Exception('"entityClass" has to be a children of ' . OrmEntity::className());
        }
        self::select($entityClass, $params);
        $result = self::result();
        $items  = [];
        foreach ($result as $item) {
            $items[] = new $entityClass($item, true);
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

        return $this->exec(CrudDbInterface::OPERATION_SELECT, $entity);
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
        $wasSuccess = $this->make(
            CrudDbInterface::OPERATION_INSERT,
            $entity,
            $asTransaction
        );
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
        $wasSuccess = $this->make(
            CrudDbInterface::OPERATION_UPDATE,
            $entity,
            $asTransaction
        );
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
        $wasSuccess = $this->make(
            CrudDbInterface::OPERATION_DELETE,
            $entity,
            $asTransaction
        );
        $entity->afterDelete($wasSuccess);

        return $wasSuccess;
    }

    /**
     * @param OrmEntity $entity
     * @param bool      $asTransaction
     *
     * @return bool
     */
    public function upsert(OrmEntity $entity, $asTransaction = true)
    {
        $entity->beforeUpsert();
        $wasSuccess = $this->make(
            CrudDbInterface::OPERATION_UPSERT,
            $entity,
            $asTransaction
        );
        $entity->afterUpsert($wasSuccess);

        return $wasSuccess;
    }

    /**
     * @param OrmEntity $entity
     * @param bool      $asTransaction
     *
     * @return bool
     */
    public function save(OrmEntity $entity, $asTransaction = true)
    {
        $entity->beforeSave();
        $wasSuccess = $this->make(
            CrudDbInterface::OPERATION_SAVE,
            $entity,
            $asTransaction
        );
        $entity->afterSave($wasSuccess);

        return $wasSuccess;
    }

    /**
     * @param OrmEntity $what
     * @param OrmEntity $from
     *
     * @return bool
     */
    public function unlink(OrmEntity $what, OrmEntity $from)
    {
        $rootWrapper = EntityPreparer::getWrapper($from);
        $kidWrapper  = EntityPreparer::getWrapper($what);
        WrappersLinking::connect($rootWrapper, $kidWrapper);

        return $this->storage->unlink($kidWrapper);
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

    protected function init()
    {
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