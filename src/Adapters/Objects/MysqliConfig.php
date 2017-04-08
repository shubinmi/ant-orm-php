<?php

namespace AntOrm\Adapters\Objects;

final class MysqliConfig extends StorageConfig
{
    /**
     * @var string
     */
    public $host;

    /**
     * @var string
     */
    public $user;

    /**
     * @var string
     */
    public $pass;

    /**
     * @var string
     */
    public $db;
}