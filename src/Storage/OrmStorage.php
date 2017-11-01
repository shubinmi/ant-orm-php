<?php

namespace AntOrm\Storage;

use AntOrm\Adapters\AdapterInterface;
use AntOrm\Adapters\MysqliAdapter;
use AntOrm\Adapters\Objects\StorageConfig;
use AntOrm\Entity\EntityWrapper;
use AntOrm\Entity\Helpers\EntityPreparer;
use AntOrm\Entity\Helpers\WrappersLinking;
use AntOrm\Entity\OrmEntity;
use AntOrm\QueryRules\CrudDbInterface;
use AntOrm\Storage\Singleton\QueriesQueueForDeleteStorage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class OrmStorage implements CrudDbInterface, LoggerAwareInterface
{
    const ALIAS_MYSQLI = 'mysqli';

    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var array
     */
    private $availableOperations;

    /**
     * @var array
     */
    private $mapOfAdapters = [
        self::ALIAS_MYSQLI => MysqliAdapter::class
    ];

    public function __clone()
    {
        $this->adapter = clone $this->adapter;
    }

    /**
     * @param string              $adapterName
     * @param array|StorageConfig $config
     *
     * @throws \Exception
     */
    public function __construct($adapterName, $config)
    {
        $adapterName = strtolower($adapterName);
        try {
            if (empty($this->mapOfAdapters[$adapterName])) {
                throw new \Exception("Incorrect adapter name: {$adapterName}");
            }
            $adapterClass = '\\' . $this->mapOfAdapters[$adapterName];
            $adapter      = new $adapterClass($config);
        } catch (\Exception $e) {
            throw new \Exception("Storage adapter Class error: {$e->getMessage()}");
        }
        $this->adapter             = $adapter;
        $this->availableOperations = get_class_methods('AntOrm\QueryRules\CrudDbInterface');
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->adapter->setLogger($logger);
        return $this;
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function select(EntityWrapper $wrapper)
    {
        return $this->adapter->select($wrapper);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function insert(EntityWrapper $wrapper)
    {
        if (!$this->adapter->insert($wrapper)) {
            return false;
        }

        return $this->sameForRelatedEntities('insert', $wrapper);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function upsert(EntityWrapper $wrapper)
    {
        if (!$this->adapter->upsert($wrapper)) {
            return false;
        }

        return $this->sameForRelatedEntities('upsert', $wrapper);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function save(EntityWrapper $wrapper)
    {
        if (!$wrapper->getEntity()->oldParams) {
            $result = $this->adapter->insert($wrapper);
        } else {
            $result = $this->adapter->update($wrapper);
        }
        if (!$result) {
            return false;
        }

        return $this->sameForRelatedEntities('save', $wrapper);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function update(EntityWrapper $wrapper)
    {
        if (!$this->adapter->update($wrapper)) {
            return false;
        }

        return $this->sameForRelatedEntities('update', $wrapper);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function unlink(EntityWrapper $wrapper)
    {
        return $this->adapter->unlink($wrapper);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function delete(EntityWrapper $wrapper)
    {
        if (QueriesQueueForDeleteStorage::has($wrapper->getEntity())) {
            return true;
        }
        QueriesQueueForDeleteStorage::add($wrapper->getEntity());
        if ($wrapper->isMeToParentAsIHaveMany()) {
            return $this->adapter->delete($wrapper);
        }
        if (!$this->sameForRelatedEntities('delete', $wrapper, false)) {
            return false;
        }
        if (QueriesQueueForDeleteStorage::itFirst($wrapper->getEntity())) {
            QueriesQueueForDeleteStorage::clear();
        }

        return $this->adapter->delete($wrapper);
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @return bool
     */
    public function onTransaction()
    {
        return $this->adapter->onTransaction();
    }

    /**
     * @return bool
     */
    public function startTransaction()
    {
        return $this->adapter->startTransaction();
    }

    /**
     * @return bool
     */
    public function endTransactions()
    {
        return $this->adapter->endTransaction();
    }

    /**
     * @param string        $operation
     * @param EntityWrapper $wrapper
     *
     * @return bool
     * @throws \Exception
     */
    public function make($operation, EntityWrapper $wrapper)
    {
        if (!in_array($operation, $this->availableOperations)) {
            throw new \Exception("Wrong operation: '{$operation}'");
        }
        return $this->$operation($wrapper);
    }

    /**
     * @param string        $method
     * @param EntityWrapper $wrapper
     * @param bool          $useValuesInsteadOfProperties
     *
     * @return bool
     */
    private function sameForRelatedEntities($method, EntityWrapper $wrapper, $useValuesInsteadOfProperties = true)
    {
        $properties = $wrapper->getPreparedProperties();
        $result     = true;
        foreach ($properties as $property) {
            if ($useValuesInsteadOfProperties && !isset($property->value)) {
                continue;
            }
            if (!$property->metaData->getRelated()) {
                continue;
            }
            $myChild = $property->value;
            if (!$useValuesInsteadOfProperties && empty($myChild)) {
                $myChild = $property->metaData->getRelated()->getWith();
            }
            if ($myChild instanceof OrmEntity) {
                $result = $result && $this->$method($this->getChildWrapper($wrapper, $myChild));
            } elseif (is_array($myChild)) {
                foreach ($myChild as $childEntity) {
                    if ($childEntity instanceof OrmEntity) {
                        $result = $result && $this->$method($this->getChildWrapper($wrapper, $childEntity));
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param EntityWrapper $myWrapper
     * @param OrmEntity     $myChildEntity
     *
     * @return EntityWrapper
     */
    private function getChildWrapper(EntityWrapper $myWrapper, OrmEntity $myChildEntity)
    {
        $childWrapper = EntityPreparer::getWrapper($myChildEntity);
        WrappersLinking::connect($myWrapper, $childWrapper);
        return $childWrapper;
    }
}