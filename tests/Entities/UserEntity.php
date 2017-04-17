<?php

namespace AntOrm\Tests\Entities;

use AntOrm\Entity\OrmEntity;

/**
 * @orm{"table":"users", "primaryProperties": ["id"]}
 */
class UserEntity extends OrmEntity
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $email;

    /**
     * @var int
     */
    public $status;

    /**
     * @var ProfileEntity
     * @orm{"related":{"with":"\AntOrm\Tests\Entities\ProfileEntity", "as":"hasOne", "onMyColumn":"id", "onHisColumn":"user_id"}}
     */
    public $profile;

    /**
     * @var HobbyEntity[]
     * @orm{"related":{"with":"\AntOrm\Tests\Entities\HobbyEntity", "as":"hasMany", "onMyColumn":"id", "onHisColumn":"id", "by":{"table":"users_hobbies", "myColumn":"user_id", "relatedColumn":"hobby_id"}}}
     */
    public $hobbies;
}