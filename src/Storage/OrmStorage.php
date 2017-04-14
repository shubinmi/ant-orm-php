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

class OrmStorage implements CrudDbInterface
{
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
        'mysqli' => MysqliAdapter::class
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
     * @param string        $method
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function sameForRelatedEntities($method, EntityWrapper $wrapper)
    {
        $properties = $wrapper->getPreparedProperties();
        foreach ($properties as $property) {
            if (!isset($property->value) || !$property->metaData->getRelated()) {
                continue;
            }
            if ($property->value instanceof OrmEntity) {
                return $this->$method($this->getLinkedWrapper($wrapper, $property->value));
            } elseif (is_array($property->value)) {
                foreach ($property->value as $item) {
                    if ($item instanceof OrmEntity) {
                        return $this->$method($this->getLinkedWrapper($wrapper, $item));
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param EntityWrapper $wrapper
     * @param OrmEntity     $entity
     *
     * @return EntityWrapper
     */
    private function getLinkedWrapper(EntityWrapper $wrapper, OrmEntity $entity)
    {
        $relatedWrapper = EntityPreparer::getWrapper($entity);
        WrappersLinking::connect($wrapper, $relatedWrapper);
        return $relatedWrapper;
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
    public function delete(EntityWrapper $wrapper)
    {
        if (!$this->sameForRelatedEntities('delete', $wrapper)) {
            return false;
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
}