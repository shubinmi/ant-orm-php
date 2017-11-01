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
    public function testCheckInsert(OrmRepository $repo)
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

        $this->assertCount(2, $user->profile->addresses);
        $mustHave = array_flip(['123123', '456456']);
        foreach ($user->profile->addresses as $address) {
            unset($mustHave[$address->zip]);
        }
        $this->assertTrue(empty($mustHave));

        return $repo;
    }

    /**
     * @depends testCheckInsert
     *
     * @param OrmRepository $repo
     *
     * @return OrmRepository
     */
    public function testUpdate(OrmRepository $repo)
    {
        $hobby1       = new HobbyEntity();
        $hobby1->id   = 2;
        $hobby2       = new HobbyEntity();
        $hobby2->name = 'Poker';
        $hobbies      = [$hobby1, $hobby2];

        $address1          = new AddressEntity();
        $address1->zip     = '100001';
        $address1->address = 'Freedom';
        $addresses         = [$address1];

        /** @var UserEntity $user */
        $user                     = current($repo->find(['email' => 'new@qa.io']));
        $user->email              = 'update@qa.io';
        $user->profile->firstName = 'Updated';
        foreach ($user->hobbies as $hobby) {
            if ($hobby->id == 1) {
                $hobbies[] = $hobby;
                break;
            }
        }
        $user->hobbies = $hobbies;
        foreach ($user->profile->addresses as $address) {
            if ($address->zip == '123123') {
                $address->address = 'SPb';
                $addresses[]      = $address;
            }
        }
        $user->profile->addresses = $addresses;

        $made = $repo->update($user);
        $this->assertTrue($made);

        return $repo;
    }

    /**
     * @depends testUpdate
     *
     * @param OrmRepository $repo
     *
     * @return OrmRepository
     */
    public function testCheckUpdate(OrmRepository $repo)
    {
        /** @var UserEntity $user */
        $user = current($repo->find(['email' => 'update@qa.io']));
        $this->assertEquals(1, $user->status);
        $this->assertCount(4, (array)$user->hobbies);
        $this->assertCount(3, (array)$user->profile->addresses);
        $this->assertEquals(
            'Updated QA', $user->profile->firstName . ' ' . $user->profile->lastName
        );
        $mustHave = array_flip(['Fishing', 'TV', 'Poker', 'Soccer']);
        foreach ($user->hobbies as $hobby) {
            unset($mustHave[$hobby->name]);
        }
        $this->assertTrue(empty($mustHave));
        $mustHave = array_flip(['123123', '456456', '100001']);
        foreach ($user->profile->addresses as $address) {
            if ($address->zip == '123123') {
                $this->assertEquals('SPb', $address->address);
            }
            unset($mustHave[$address->zip]);
        }
        $this->assertTrue(empty($mustHave));

        return $repo;
    }

    /**
     * @depends testCheckUpdate
     *
     * @param OrmRepository $repo
     *
     * @return OrmRepository
     */
    public function testUpsert(OrmRepository $repo)
    {
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
        $user->email   = 'upsert@qa.io';
        $user->status  = 1;
        $user->profile = $profile;
        $user->hobbies = [$hobby1, $hobby2];

        $upserted = $repo->upsert($user);
        $this->assertTrue($upserted);

        /** @var UserEntity $user */
        $user        = current($repo->find(['email' => 'update@qa.io']));
        $user->email = 'new@qa.io';

        $upserted = $repo->upsert($user);
        $this->assertTrue($upserted);

        return $repo;
    }

    /**
     * @depends testUpsert
     *
     * @param OrmRepository $repo
     *
     * @return OrmRepository
     */
    public function testCheckUpsert(OrmRepository $repo)
    {
        /** @var UserEntity[] $users */
        $users = $repo->find(['email in' => "('new@qa.io', 'upsert@qa.io')"]);
        $this->assertCount(2, $users);
        foreach ($users as $user) {
            if ($user->email == 'upsert@qa.io') {
                $repo->delete($user);
            }
        }

        return $repo;
    }

    /**
     * @depends testCheckUpsert
     *
     * @param OrmRepository $repo
     *
     * @return OrmRepository
     */
    public function testSave(OrmRepository $repo)
    {
        /** @var UserEntity $user */
        $user             = current($repo->find(['email' => 'new@qa.io']));
        $oldUserFName     = $user->profile->firstName;
        $oldUserAddresses = $user->profile->addresses;
        $oldUserHobbies   = $user->hobbies;
        $oldUserStatus    = $user->status;

        $newAddress          = new AddressEntity();
        $newAddress->address = 'Tomsk';
        $newAddress->zip     = '324123';

        $newHobby       = new HobbyEntity();
        $newHobby->name = 'Magic';

        $user->profile->firstName = 'Saved';
        $user->status             = $oldUserStatus + 1;
        $user->hobbies            = [$newHobby];
        $addresses                = [$newAddress];
        foreach ($oldUserAddresses as $address) {
            if ($address->zip == '123123') {
                $address->address = 'Saint-Petersburg';
                $addresses[]      = $address;
            }
        }
        $user->profile->addresses = $addresses;

        $saved = $repo->save($user);
        $this->assertTrue($saved);

        /** @var UserEntity $user */
        $user = current($repo->find(['email' => 'new@qa.io']));
        $this->assertTrue($user->profile->firstName == 'Saved');
        $this->assertCount(count($oldUserAddresses) + 1, $user->profile->addresses);
        $this->assertCount(count($oldUserHobbies) + 1, $user->hobbies);
        $this->assertEquals($oldUserStatus + 1, $user->status);
        $successAddresses = 0;
        foreach ($user->profile->addresses as $address) {
            if ($address->zip == '123123') {
                if ($address->address == 'Saint-Petersburg') {
                    ++$successAddresses;
                }
                continue;
            }
            if (in_array($address->zip, ['324123',])) {
                if ($address->address == 'Tomsk') {
                    ++$successAddresses;
                }
                continue;
            }
            ++$successAddresses;
        }
        $this->assertEquals(count($oldUserAddresses) + 1, $successAddresses);

        $user->status             = $oldUserStatus;
        $user->profile->firstName = $oldUserFName;
        $repo->save($user);

        return $repo;
    }

    /**
     * @depends testSave
     *
     * @param OrmRepository $repo
     *
     * @return OrmRepository
     */
    public function testUnlink(OrmRepository $repo)
    {
        /** @var UserEntity $user */
        $user           = current($repo->find(['email' => 'new@qa.io']));
        $addressesCount = count($user->profile->addresses);
        $hobbiesCount   = count($user->hobbies);

        // Unlink OneHasMany
        /** @var AddressEntity $address */
        $address  = current($repo->find(['zip' => '324123'], new AddressEntity()));
        $unlinked = $repo->delete($address);
        $this->assertTrue($unlinked);

        // Unlink ManyHasMany
        /** @var HobbyEntity $user */
        $hobby    = current($repo->find(['name' => 'Magic'], new HobbyEntity()));
        $unlinked = $repo->unlink($hobby, $user);
        $this->assertTrue($unlinked);

        /** @var UserEntity $user */
        $user = current($repo->find(['email' => 'new@qa.io']));
        $this->assertCount($addressesCount - 1, (array)$user->profile->addresses);
        $this->assertCount($hobbiesCount - 1, (array)$user->hobbies);

        $this->assertTrue($repo->delete($hobby));

        return $repo;
    }

    /**
     * @depends testUnlink
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