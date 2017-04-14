<?php

namespace AntOrm\Tests\Mysql;

use AntOrm\Repository\OrmRepository;
use AntOrm\Storage\OrmStorage;
use PHPUnit\Framework\TestCase;

class RepositoryInsertTest extends TestCase
{
    public function testInsertOnoToOne()
    {
        /** @var OrmStorage $storage */
        global $storage;
        $repo = new OrmRepository(clone $storage, UserEntity::className());
        $repo->find();

        $profile            = new ProfileEntity();
        $profile->lastName  = 'QA';
        $profile->firstName = 'New';
        $user               = new UserEntity();
        $user->email        = 'new@qa.io';
        $user->status       = 1;
        $user->profile      = $profile;
        $inserted           = $repo->insert($user);
        $this->assertEquals(true, $inserted);
    }
}