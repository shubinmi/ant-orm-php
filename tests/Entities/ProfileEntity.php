<?php

namespace AntOrm\Tests\Entities;

use AntOrm\Entity\OrmEntity;

/**
 * @orm{"table":"profiles"}
 */
class ProfileEntity extends OrmEntity
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
     * @orm{"column":"first_name"}
     */
    public $firstName;

    /**
     * @var string
     * @orm{"column":"last_name"}
     */
    public $lastName;

    /**
     * @var AddressEntity[]
     * @orm{"related":{"with":"\AntOrm\Tests\Entities\AddressEntity", "as":"hasMany", "onMyColumn":"user_id", "onHisColumn":"user_id"}}
     */
    public $addresses;
}