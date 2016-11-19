<?php

namespace AntOrm\Adapters;

interface AdapterInterface
{
    public function __construct(array $config);
    public function query($query, $bindPattern = null, array $bindParams = null);
    public function result();
    public function getLastInsertId();
    public function getLastResult();
    public function closeConnect();
}