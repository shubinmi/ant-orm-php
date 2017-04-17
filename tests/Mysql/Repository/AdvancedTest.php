<?php

namespace AntOrm\Tests\Mysql;

use AntOrm\Repository\OrmRepository;
use AntOrm\Storage\OrmStorage;
use AntOrm\Tests\Entities\ProfileEntity;
use AntOrm\Tests\Entities\UserEntity;
use PHPUnit\Framework\TestCase;

class AdvancedTest extends TestCase
{
    public function testInsert()
    {
        /** @var OrmStorage $storage */
        global $storage;
        $repo = new OrmRepository(clone $storage, UserEntity::className());

        $profile            = new ProfileEntity();
        $profile->lastName  = 'QA';
        $profile->firstName = 'New';
        $user               = new UserEntity();
        $user->email        = 'new@qa.io';
        $user->status       = 1;
        $user->profile      = $profile;
        $inserted           = $repo->insert($user);
        $this->assertEquals(true, $inserted);

        return $repo;
    }

    /**
     * @depends testInsert
     *
     * @param OrmRepository $repo
     */
    public function testDelete(OrmRepository $repo)
    {
        $users    = $repo->find(['email' => 'new@qa.io']);
        $usersIds = [];
        /** @var UserEntity $user */
        foreach ($users as $user) {
            $usersIds[] = $user->id;
            $deleted    = $repo->delete($user);
            $this->assertEquals(true, $deleted);
        }
        $usersIds = implode(',', $usersIds);
        $selected =
            $repo->select(ProfileEntity::class, ['user_id in' => "({$usersIds})"]);
        $this->assertEquals(true, $selected);
        $result = $repo->result();
        $this->assertCount(0, $result);
    }
}