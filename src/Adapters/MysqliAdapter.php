<?php

namespace AntOrm\Adapters;

use AntOrm\QueryRules\QueryStructure;
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
     * @var \stdClass[]
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
     * MysqliAdapter constructor.
     *
     * @param array $config
     *
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        $config        = (object)$config;
        $this->adapter = new \mysqli($config->host, $config->user, $config->pass, $config->db);
        if (mysqli_connect_errno()) {
            throw new \Exception("Can't connect to db:\n" . mysqli_connect_error());
        }
        $this->adapter->set_charset("utf8");
        $this->adapter->autocommit(true);
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
    public function endTransaction()
    {
        $transaction = new TransactionQueryList();
        $transaction->addQuery((new QueryStructure())->setQuery('start transaction'));
        foreach ($this->queries as $query) {
            $transaction->addQuery($query);
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
        $this->stmt = [];

        if ($query instanceof TransactionQueryList) {
            /** @var TransactionQueryList $query */
            foreach ($query->getQueries() as $q) {
                $this->prepareQuery($q);
            }
        } elseif ($query instanceof QueryStructure) {
            $this->prepareQuery($query);
        }
        foreach ($this->stmt as $stmt) {
            if ($stmt->execute() === false) {
                if ($this->transactionWaitingCommit) {
                    $this->rollbackTransactions();
                }
                throw new \Exception('Query failed: ' . $this->adapter->error);
            }
        }
        if ($this->transactionWaitingCommit) {
            $this->transactionWaitingCommit = false;
            if (!$this->adapter->commit()) {
                throw new \Exception('Transaction failed: ' . $this->adapter->error);
            }
            $this->adapter->autocommit(true);
        }

        return true;
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
                $this->transactionWaitingCommit = true;
                $this->adapter->autocommit(false);
                continue;
            }
            $stmt = $this->adapter->prepare($sql);
            if ($stmt === false) {
                throw new \Exception('Incorrect sql for mysqli::prepare : ' . $sql);
            }
            if (!empty($query->getBindParams())) {
                $params = array_merge(
                    [implode('', $query->getBindPatterns())],
                    $query->getBindParams()
                );
                call_user_func_array(
                    [$stmt, 'bind_param'],
                    array_map(
                        function &(&$value) {
                            return $value;
                        },
                        $params
                    )
                );
            }
            $this->stmt[] = $stmt;
        }
    }

    /**
     * @return \stdClass[]
     */
    public function result()
    {
        $this->result = [];
        $result       = $this->stmt[count($this->stmt) - 1]->get_result();
        if (!$result instanceof \mysqli_result) {
            return [];
        }

        $fieldInfo = $result->fetch_fields();
        $fieldInfo = $fieldInfo ?: [];

        $i         = 0;
        $fieldName = [];
        $table     = '';
        foreach ($fieldInfo as $field) {
            if ($i == 0) {
                $table = $field->table;
            }
            $fieldName[$i] = $field->table == $table ? $field->name : $field->table . '.' . $field->name;
            ++$i;
        }

        $i = 0;
        while ($row = $result->fetch_row()) {
            $object = new \stdClass();
            foreach ($row as $column => $value) {
                $object->{$fieldName[$column]} = $value;
            }
            $this->result[] = $object;
            ++$i;
        }
        $result->close();

        return $this->result;
    }

    /**
     * @return int
     */
    public function getLastInsertId()
    {
        return $this->stmt[count($this->stmt) - 1]->insert_id;
    }

    /**
     * @return \stdClass[]
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
                    $stmt->close();
                }
            }
            @$this->adapter->close();
        } catch (\Exception $e) {
        }
    }
}