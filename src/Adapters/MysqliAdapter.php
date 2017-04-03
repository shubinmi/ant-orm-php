<?php

namespace AntOrm\Adapters;

use AntOrm\QueryRules\QueryStructure;

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
        $transaction    = new QueryStructure();
        $transactionSql = 'start transaction;';
        foreach ($this->queries as $query) {
            $transactionSql .= $query->getQuery() . ';';
            $bindPatterns   = array_merge($transaction->getBindPatterns(), $query->getBindPatterns());
            $bindParams     = array_merge($transaction->getBindParams(), $query->getBindParams());
            $transaction->setBindParams($bindParams);
            $transaction->setBindPatterns($bindPatterns);
        }
        $transactionSql .= 'commit;';
        $transaction->setQuery($transactionSql);
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
     * @param QueryStructure|string $query
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
        if (!$query instanceof QueryStructure) {
            throw new \InvalidArgumentException('$query param must be string or QueryStructure');
        }
        if ($this->onTransaction) {
            $this->queries[] = $query;
            return true;
        }
        $queryParts     = explode(';', $query->getQuery());
        $queryParts     = array_filter($queryParts);
        $this->stmt     = [];
        $haveToBeCommit = false;
        foreach ($queryParts as $sql) {
            $tempSql = strtolower(str_replace(' ', '', $sql));
            if ($tempSql == 'commit') {
                $haveToBeCommit = true;
                continue;
            }
            if ($tempSql == 'starttransaction') {
                $this->adapter->autocommit(false);
                continue;
            }
            $stmt = $this->adapter->prepare($sql);
            if ($stmt === false) {
                throw new \Exception('Incorrect sql for mysqli::prepare : ' . $sql);
            }
            $this->stmt[] = $stmt;
        }
        if (!empty($query->getBindParams())) {
            $params = array_merge(
                [implode('', $query->getBindPatterns())],
                $query->getBindParams()
            );
            call_user_func_array(
                [$this->stmt[count($this->stmt) - 1], 'bind_param'],
                array_map(
                    function &(&$value) {
                        return $value;
                    },
                    $params
                )
            );
        }
        foreach ($this->stmt as $stmt) {
            if ($stmt->execute() === false) {
                if ($haveToBeCommit) {
                    $this->rollbackTransactions();
                }
                throw new \Exception('Query failed: ' . $this->adapter->error);
            }
        }
        if ($haveToBeCommit) {
            $this->adapter->commit();
        }
        $this->adapter->autocommit(true);

        return true;
    }

    /**
     * @return \stdClass[]
     */
    public function result()
    {
        $this->result = [];
        $result       = $this->stmt[count($this->stmt) - 1]->get_result();
        if (!$result) {
            $result->close();
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