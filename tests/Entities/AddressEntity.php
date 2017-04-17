<?php

namespace AntOrm\Tests\Entities;

use AntOrm\Entity\OrmEntity;

/**
 * @orm{"table":"addresses"}
 */
class AddressEntity extends OrmEntity
{
    /**
     * @var int
     * @orm{"primary":true}
     */
    public $id;

    /**
     * @var int
     * @orm{"type":"INTEGER", "column":"user_id"}
     */
    public $userId;

    /**
     * @var string
     */
    public $address;

    /**
     * @var string
     */
    public $zip;
}