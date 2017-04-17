<?php

namespace AntOrm\Tests\Entities;

use AntOrm\Entity\OrmEntity;

/**
 * @orm{"table":"hobbies", "primaryProperties": ["id"]}
 */
class HobbyEntity extends OrmEntity
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;
}