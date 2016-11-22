<?php

namespace AntOrm\Adapters;

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

    public function __construct(array $config)
    {
        $config = (object)$config;
        $this->adapter = new \mysqli($config->host, $config->user, $config->pass, $config->db);
        if (mysqli_connect_errno()) {
            throw new \Exception("Can't connect to db:\n" . mysqli_connect_error());
        }
        $this->adapter->set_charset("utf8");
    }

    function __destruct()
    {
        $this->closeConnect();
        unset($this);
    }

    /**
     * @param string $query
     * @param string $bindPattern
     * @param array  $bindParams
     *
     * @return bool
     * @throws \Exception
     */
    public function query($query, $bindPattern = '', array $bindParams = [])
    {
        $queryParts = explode(';', $query);
        $queryParts = array_filter($queryParts);
        $this->stmt = [];
        foreach ($queryParts as $query) {
            $queryTemp = strtolower(str_replace(' ', '', $query));
            if ($queryTemp == 'commit') {
                continue;
            }
            if ($queryTemp == 'starttransaction') {
                $this->adapter->autocommit(false);
                continue;
            }
            $stmt = $this->adapter->prepare($query);
            if ($stmt === false) {
                throw new \Exception('Incorrect sql for mysqli::prepare : ' . $query);
            }
            $this->stmt[] = $stmt;
        }


        if ($bindPattern) {
            $params = [$bindPattern];
            foreach ($bindParams as $key => $param) {
                $params[] = &$bindParams[$key];
            }
            call_user_func_array([$this->stmt[count($this->stmt) - 1], 'bind_param'], $params);
        }

        foreach ($this->stmt as $stmt) {
            if ($stmt->execute() === false) {
                throw new \Exception('Query failed: ' . $this->adapter->error);
            }
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
            return [];
        }

        $fieldInfo = $result->fetch_fields();
        $fieldInfo = $fieldInfo ?: [];

        $i = 0;
        $fieldName = [];
        $table = '';
        foreach ($fieldInfo as $field) {
            if ($i == 0) {
                $table = $field->table;
            }
            $fieldName[$i] = $field->table == $table ? $field->name : $field->table . '.' .$field->name;
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
        if (!empty($this->stmt)) {
            foreach ($this->stmt as $stmt) {
                $stmt->close();
            }
        }
        $this->adapter->close();
    }
}