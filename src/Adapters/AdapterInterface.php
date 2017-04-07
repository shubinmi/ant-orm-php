<?php

namespace AntOrm\Adapters;

use AntOrm\QueryRules\QueryStructure;
use AntOrm\QueryRules\TransactionQueryList;

interface AdapterInterface
{
    /**
     * @param array $config
     */
    public function __construct(array $config);

    /**
     * @param QueryStructure|string|TransactionQueryList $query
     *
     * @return bool
     */
    public function query($query);

    /**
     * @return mixed
     */
    public function result();

    /**
     * @return int|null
     */
    public function getLastInsertId();

    /**
     * @return mixed
     */
    public function getLastResult();

    /**
     * @return bool
     */
    public function startTransaction();

    /**
     * @return bool
     */
    public function endTransaction();

    /**
     * @return bool
     */
    public function rollbackTransactions();

    public function closeConnect();
}