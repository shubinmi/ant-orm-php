<?php

namespace AntOrm\Adapters;

use AntOrm\Adapters\Objects\MysqliConfig;
use AntOrm\Entity\EntityWrapper;
use AntOrm\QueryRules\QueryStructure;
use AntOrm\QueryRules\Sql\MySql;
use AntOrm\QueryRules\TransactionQueryList;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

class MysqliAdapter implements AdapterInterface
{
    use LoggerAwareTrait;

    /**
     * @var \mysqli
     */
    private $driver;

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
            $err = 'Config of mysqli storage must be array or object of MysqliConfig class';
            $this->logger->log(LogLevel::EMERGENCY, $err, ['Got ' . gettype($config)]);
            throw new \Exception($err);
        }
        $this->config = $config;
        $this->driver = new \mysqli($config->host, $config->user, $config->pass, $config->db);
        if (mysqli_connect_errno()) {
            $err = "Can't connect to db: " . mysqli_connect_error();
            $this->logger->log(LogLevel::EMERGENCY, $err, ['Got ' . gettype($config)]);
            throw new \Exception($err);
        }
        $this->driver->set_charset("utf8");
        $this->driver->autocommit(true);
        $this->sqlGenerator = new MySql();
        $this->logger       = new NullLogger();
        $this->logger->log(LogLevel::DEBUG, 'Connected to DB');
    }

    public function __clone()
    {
        $this->driver = new \mysqli($this->config->host, $this->config->user, $this->config->pass, $this->config->db);
        $this->stmt   = [];
        $this->logger->log(LogLevel::DEBUG, 'Connected to DB cloned');
    }

    public function __destruct()
    {
        $this->closeConnect();
        $this->logger->log(LogLevel::DEBUG, 'Connected to DB closed');
        unset($this);
    }

    /**
     * @return bool
     */
    public function startTransaction()
    {
        $this->onTransaction = true;
        $this->logger->log(LogLevel::DEBUG, 'Transaction started to collect queries');
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
        $this->logger->log(LogLevel::DEBUG, 'Transaction commit started');

        return $this->query($transaction);
    }

    /**
     * @return bool
     */
    public function rollbackTransactions()
    {
        $this->logger->log(LogLevel::DEBUG, 'Transaction rollback');
        return $this->driver->rollback();
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
                $this->logger->log(
                    LogLevel::DEBUG, 'Query prepared',
                    [
                        'sql'    => $q->getQuery(),
                        'params' => implode(',', $q->getBindParams()),
                        'types'  => implode(',', $q->getBindPatterns())
                    ]
                );
            }
        } elseif ($query instanceof QueryStructure) {
            $this->prepareQuery($query);
            $this->logger->log(
                LogLevel::DEBUG, 'Query prepared',
                [
                    'sql'    => $query->getQuery(),
                    'params' => implode(',', $query->getBindParams()),
                    'types'  => implode(',', $query->getBindPatterns())
                ]
            );
        }
        foreach ($this->stmt as $stmt) {
            if (!$stmt->execute()) {
                if ($this->transactionWaitingCommit) {
                    $this->transactionWaitingCommit = false;
                    $this->rollbackTransactions();
                }
                $err = 'Query error: ' . $stmt->error;
                $this->logger->log(LogLevel::ERROR, $err);
                throw new \Exception($err);
            }
            $this->logger->log(LogLevel::DEBUG, 'Query execute success');
        }
        if ($this->transactionWaitingCommit) {
            $this->transactionWaitingCommit = false;
            if (!$this->driver->commit()) {
                if ($this->transactionWaitingCommit) {
                    $this->rollbackTransactions();
                }
                $err = 'Transaction failed: ' . $this->driver->error;
                $this->logger->log(LogLevel::ERROR, $err);
                throw new \Exception($err);
            }
            $this->driver->autocommit(true);
            $this->logger->log(LogLevel::DEBUG, 'Transaction commit success');
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
    public function unlink(EntityWrapper $wrapper)
    {
        $query = $this->sqlGenerator->unlink($wrapper);
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

    public function save(EntityWrapper $wrapper)
    {
        // Not used
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
            if ($this->driver instanceof \mysqli) {
                @$this->driver->close();
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
                $this->driver->autocommit(false);
                continue;
            }
            try {
                $stmt = $this->driver->prepare($sql);
            } catch (\Exception $e) {
                $err = 'Error on mysqli::prepare for : ' . $sql . ' ; ' . $e->getMessage();
                $this->logger->log(LogLevel::ERROR, $err);
                throw new \Exception($err);
            }
            if ($stmt === false) {
                $err = 'Incorrect sql for mysqli::prepare : ' . $sql;
                $this->logger->log(LogLevel::ERROR, $err);
                throw new \Exception($err);
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
                    $err     = 'Incorrect sql for mysqli::bind_param : ' . $e->getMessage();
                    $context = [
                        'Query: ' . $sql,
                        'Bind values: ' . implode(', ', $query->getBindParams()),
                        'Bind patterns: ' . implode(', ', $query->getBindParams())
                    ];
                    $this->logger->log(LogLevel::ERROR, $err, $context);
                    throw new \Exception(
                        'Incorrect sql for mysqli::bind_param : ' . $e->getMessage() . '; '
                        . implode('; ', $context)
                    );
                }
            }
            $this->stmt[] = $stmt;
        }
    }
}