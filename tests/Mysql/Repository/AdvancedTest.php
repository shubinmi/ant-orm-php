<?php

namespace AntOrm\Tests\Mysql;

use AntOrm\Repository\OrmRepository;
use AntOrm\Storage\OrmStorage;
use AntOrm\Tests\Entities\AddressEntity;
use AntOrm\Tests\Entities\HobbyEntity;
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

        $hobby1       = new HobbyEntity();
        $hobby1->id   = 1;
        $hobby2       = new HobbyEntity();
        $hobby2->name = 'Soccer';

        $address1          = new AddressEntity();
        $address1->zip     = '123123';
        $address1->address = 'St Petersburg';
        $address2          = new AddressEntity();
        $address2->zip     = '456456';
        $address2->address = 'New York';

        $profile            = new ProfileEntity();
        $profile->lastName  = 'QA';
        $profile->firstName = 'New';
        $profile->addresses = [$address1, $address2];

        $user          = new UserEntity();
        $user->email   = 'new@qa.io';
        $user->status  = 1;
        $user->profile = $profile;
        $user->hobbies = [$hobby1, $hobby2];

        $inserted = $repo->insert($user);
        $this->assertTrue($inserted);

        return $repo;
    }

    /**
     * @depends testInsert
     *
     * @param OrmRepository $repo
     *
     * @return OrmRepository
     */
    public function testFind(OrmRepository $repo)
    {
        /** @var UserEntity $user */
        $user = current($repo->find(['email' => 'new@qa.io']));
        $this->assertEquals(1, $user->status);
        $this->assertCount(2, (array)$user->hobbies);
        $this->assertEquals(
            'New QA', $user->profile->firstName . ' ' . $user->profile->lastName
        );
        $mustHave = array_flip(['Fishing', 'Soccer']);
        foreach ($user->hobbies as $hobby) {
            unset($mustHave[$hobby->name]);
        }
        $this->assertTrue(empty($mustHave));
        $mustHave = array_flip(['123123', '456456']);
        foreach ($user->profile->addresses as $address) {
            unset($mustHave[$address->zip]);
        }
        $this->assertTrue(empty($mustHave));

        return $repo;
    }

    /**
     * @depends testFind
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
        $this->assertTrue($selected);
        $result = $repo->result();
        $this->assertCount(0, $result);

        $selected =
            $repo->select(AddressEntity::class, ['user_id in' => "({$usersIds})"]);
        $this->assertTrue($selected);
        $result = $repo->result();
        $this->assertCount(0, $result);

        $selected =
            $repo->select(HobbyEntity::class, ['name' => 'Soccer']);
        $this->assertTrue($selected);
        $result = $repo->result();
        $this->assertCount(1, $result);
        $hobby   = new HobbyEntity(current($result));
        $deleted = $repo->delete($hobby);
        $this->assertEquals(true, $deleted);
    }
}