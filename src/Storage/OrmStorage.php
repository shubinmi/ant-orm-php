<?php

namespace AntOrm\Storage;

use AntOrm\Adapters\AdapterInterface;
use AntOrm\Entity\EntityWrapper;
use AntOrm\QueryRules\QueryPrepareInterface;

class OrmStorage
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
    private $mapperAdapterToQueryRule = [
        'mysqli' => 'Sql\MySql'
    ];

    /**
     * @var QueryPrepareInterface
     */
    private $queryRule;

    public function __clone()
    {
        $this->adapter   = clone $this->adapter;
        $this->queryRule = clone $this->queryRule;
    }

    /**
     * @param string $adapterName
     * @param array  $config
     *
     * @throws \Exception
     */
    public function __construct($adapterName, array $config)
    {
        $adapterName  = strtolower($adapterName);
        $adapterClass = '\AntOrm\Adapters\\' . ucfirst($adapterName) . 'Adapter';
        try {
            if (!class_exists($adapterClass)) {
                throw new \Exception("Incorrect adapter name: {$adapterClass}");
            }
            $adapter = new $adapterClass($config);
        } catch (\Exception $e) {
            throw new \Exception("DB error: {$e->getMessage()}");
        }

        if (empty($this->mapperAdapterToQueryRule[$adapterName])) {
            throw new \Exception("Has not query rule as: {$adapterName}");
        }

        $queryRuleClass = '\AntOrm\QueryRules\\' . ucfirst($this->mapperAdapterToQueryRule[$adapterName]);

        $this->queryRule           = new $queryRuleClass();
        $this->adapter             = $adapter;
        $this->availableOperations = get_class_methods('AntOrm\Repository\RepositoryInterface');
    }

    /**
     * @param string        $operation
     * @param EntityWrapper $wrapper
     *
     * @return bool
     * @throws \Exception
     */
    public function query($operation, EntityWrapper $wrapper)
    {
        if (!in_array($operation, $this->availableOperations)) {
            throw new \Exception("Wrong operation: '{$operation}'");
        }
        $query = $this->queryRule->prepare($operation, $wrapper);
        return $this->adapter->query($query);
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