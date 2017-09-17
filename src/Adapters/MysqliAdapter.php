<?php

namespace AntOrm\Adapters;

use AntOrm\Adapters\Objects\MysqliConfig;
use AntOrm\Entity\EntityWrapper;
use AntOrm\QueryRules\QueryStructure;
use AntOrm\QueryRules\Sql\MySql;
use AntOrm\QueryRules\TransactionQueryList;

class MysqliAdapter implements AdapterInterface
{
    /**
     * @var \mysqli
     */
    private $adapter;

    /**
     * @var \mysqli_stmt[]
     */
    private $stmt;

    /**
     * @var array[]
     */
    private $result;

    /**
     * @var QueryStructure[]
     */
    private $queries = [];

    /**
     * @var bool
     */
    private $onTransaction = false;

    /**
     * @var bool
     */
    private $transactionWaitingCommit = false;

    /**
     * @var MysqliConfig
     */
    private $config;

    /**
     * @var MySql
     */
    private $sqlGenerator;

    /**
     * MysqliAdapter constructor.
     *
     * @param array|MysqliConfig $config
     *
     * @throws \Exception
     */
    public function __construct($config)
    {
        if (is_array($config)) {
            $config = new MysqliConfig($config);
        } elseif (!$config instanceof MysqliConfig) {
            throw new \Exception('Config of mysqli storage must be array or object of MysqliConfig class');
        }
        $this->config  = $config;
        $this->adapter = new \mysqli($config->host, $config->user, $config->pass, $config->db);
        if (mysqli_connect_errno()) {
            throw new \Exception("Can't connect to db:\n" . mysqli_connect_error());
        }
        $this->adapter->set_charset("utf8");
        $this->adapter->autocommit(true);
        $this->sqlGenerator = new MySql();
    }

    function __clone()
    {
        $this->adapter = new \mysqli($this->config->host, $this->config->user, $this->config->pass, $this->config->db);
        $this->stmt    = [];
    }

    public function __destruct()
    {
        $this->closeConnect();
        unset($this);
    }

    /**
     * @return bool
     */
    public function startTransaction()
    {
        $this->onTransaction = true;
        return true;
    }

    /**
     * @return bool
     */
    public function onTransaction()
    {
        return $this->onTransaction;
    }

    /**
     * @return bool
     */
    public function endTransaction()
    {
        $transaction = new TransactionQueryList();
        $transaction->addQuery((new QueryStructure())->setQuery('start transaction'));
        foreach ($this->queries as $query) {
            if ($query instanceof TransactionQueryList) {
                foreach ($query->getQueries() as $structure) {
                    $transaction->addQuery($structure);
                }
            } else {
                $transaction->addQuery($query);
            }
        }
        $transaction->addQuery((new QueryStructure())->setQuery('commit'));
        $this->onTransaction = false;

        return $this->query($transaction);
    }

    /**
     * @return bool
     */
    public function rollbackTransactions()
    {
        return $this->adapter->rollback();
    }

    /**
     * @param QueryStructure|string|TransactionQueryList $query
     *
     * @return bool
     * @throws \Exception
     */
    public function query($query)
    {
        if (is_string($query)) {
            $sql   = $query;
            $query = (new QueryStructure)->setQuery($sql);
        }
        if (!$query instanceof QueryStructure && !$query instanceof TransactionQueryList) {
            throw new \InvalidArgumentException(
                '$query param must be string or QueryStructure or TransactionQueryList.'
            );
        }
        if ($this->onTransaction) {
            $this->queries[] = $query;
            return true;
        }
        $this->queries = [];
        $this->stmt    = [];

        if ($query instanceof TransactionQueryList) {
            /** @var TransactionQueryList $query */
            foreach ($query->getQueries() as $q) {
                $this->prepareQuery($q);
            }
        } elseif ($query instanceof QueryStructure) {
            $this->prepareQuery($query);
        }
        foreach ($this->stmt as $stmt) {
            if (!$stmt->execute()) {
                if ($this->transactionWaitingCommit) {
                    $this->transactionWaitingCommit = false;
                    $this->rollbackTransactions();
                }
                throw new \Exception('Query error: ' . $stmt->error);
            }
        }
        if ($this->transactionWaitingCommit) {
            $this->transactionWaitingCommit = false;
            if (!$this->adapter->commit()) {
                if ($this->transactionWaitingCommit) {
                    $this->rollbackTransactions();
                }
                throw new \Exception('Transaction failed: ' . $this->adapter->error);
            }
            $this->adapter->autocommit(true);
        }

        return true;
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function select(EntityWrapper $wrapper)
    {
        $query = $this->sqlGenerator->select($wrapper);
        return $this->query($query);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function insert(EntityWrapper $wrapper)
    {
        $query = $this->sqlGenerator->insert($wrapper);
        return $this->query($query);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function upsert(EntityWrapper $wrapper)
    {
        $queryIgnoreInsert = $this->sqlGenerator->upsert($wrapper);
        $queryUpdate       = $this->sqlGenerator->update($wrapper);
        return $this->query($queryIgnoreInsert) && $this->query($queryUpdate);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function update(EntityWrapper $wrapper)
    {
        $query = $this->sqlGenerator->update($wrapper);
        return $this->query($query);
    }

    /**
     * @param EntityWrapper $wrapper
     *
     * @return bool
     */
    public function delete(EntityWrapper $wrapper)
    {
        $query = $this->sqlGenerator->delete($wrapper);
        return $this->query($query);
    }

    /**
     * @return array[]
     */
    public function result()
    {
        if (empty($this->stmt[count($this->stmt) - 1])) {
            return [];
        }
        $this->result = [];
        $result       = $this->stmt[count($this->stmt) - 1]->get_result();
        if (!$result instanceof \mysqli_result) {
            return [];
        }
        $tree = [];
        while ($row = $result->fetch_assoc()) {
            foreach ($row as $column => $value) {
                $columnParts = explode(MySql::SELECT_PREFIX_SEPARATOR, $column);
                $firsKey     = array_shift($columnParts);
                $property    = end($columnParts);
                if ($property == MySql::SELECT_PRIMARY_KEY) {
                    continue;
                }
                unset($columnParts[count($columnParts) - 1]);
                $v = $row[$firsKey . MySql::SELECT_PREFIX_SEPARATOR . MySql::SELECT_PRIMARY_KEY];
                if (empty($tree[$v])) {
                    $tree[$v] = [];
                }
                $element = &$tree[$v];
                $key     = $firsKey;
                foreach ($columnParts as $part) {
                    $key  .= MySql::SELECT_PREFIX_SEPARATOR . $part;
                    $pKey = $key . MySql::SELECT_PREFIX_SEPARATOR . MySql::SELECT_PRIMARY_KEY;
                    $v    = $row[$pKey];
                    if (empty($v)) {
                        break;
                    }
                    if (empty($element[$part][$v])) {
                        $element[$part][$v] = [];
                    }
                    $element = &$element[$part][$v];
                }
                if (empty($v)) {
                    continue;
                }
                $element[$property] = $value;
            }
        }
        $this->result = $tree;
        $result->close();

        return $this->result;
    }

    /**
     * @return int
     */
    public function getLastInsertId()
    {
        $i = count($this->stmt);
        while ($i > 0) {
            --$i;
            if ($this->stmt[$i]->insert_id > 0) {
                return $this->stmt[$i]->insert_id;
            }
        }

        return 0;
    }

    /**
     * @return array[]
     */
    public function getLastResult()
    {
        return $this->result;
    }

    public function closeConnect()
    {
        try {
            if (!empty($this->stmt)) {
                foreach ($this->stmt as $stmt) {
                    if ($stmt instanceof \mysqli_stmt) {
                        @$stmt->close();
                    }
                }
            }
            if ($this->adapter instanceof \mysqli) {
                @$this->adapter->close();
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * @param QueryStructure $query
     *
     * @throws \Exception
     */
    private function prepareQuery(QueryStructure $query)
    {
        $queryParts = explode(';', $query->getQuery());
        $queryParts = array_filter($queryParts);

        foreach ($queryParts as $sql) {
            $tempSql = strtolower(str_replace(' ', '', $sql));
            if ($tempSql == 'commit') {
                $this->transactionWaitingCommit = true;
                continue;
            }
            if ($tempSql == 'starttransaction') {
                $this->adapter->autocommit(false);
                continue;
            }
            try {
                $stmt = $this->adapter->prepare($sql);
            } catch (\Exception $e) {
                throw new \Exception('Error on mysqli::prepare for : ' . $sql . ' ; ' . $e->getMessage());
            }
            if ($stmt === false) {
                throw new \Exception('Incorrect sql for mysqli::prepare : ' . $sql);
            }
            if (!empty($query->getBindParams())) {
                $params = array_merge(
                    [implode('', $query->getBindPatterns())],
                    $query->getBindParams()
                );
                try {
                    call_user_func_array(
                        [$stmt, 'bind_param'],
                        array_map(
                            function &(&$value) {
                                return $value;
                            },
                            $params
                        )
                    );
                } catch (\Exception $e) {
                    throw new \Exception(
                        'Incorrect sql for mysqli::bind_param : ' . $sql
                        . PHP_EOL . 'Bind patterns: ' . implode(', ', $query->getBindPatterns())
                        . PHP_EOL . 'Bind values: ' . implode(', ', $query->getBindParams())
                        . PHP_EOL . $e->getMessage()
                    );
                }
            }
            $this->stmt[] = $stmt;
        }
    }
}