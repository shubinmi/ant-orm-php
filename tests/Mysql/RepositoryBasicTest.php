<?php

namespace AntOrm\Tests\Mysql;

use AntOrm\Repository\OrmRepository;
use AntOrm\Storage\OrmStorage;
use PHPUnit\Framework\TestCase;

class RepositoryBasicTest extends TestCase
{
    /**
     * @return OrmRepository
     */
    public function testFind()
    {
        /** @var OrmStorage $storage */
        global $storage;
        $repo  = new OrmRepository($storage, UserEntity::className());
        $users = $repo->find();
        $this->assertContainsOnlyInstancesOf(UserEntity::class, $users);
        $users = $repo->find(['id' => 1]);
        $this->assertCount(1, $users);

        return $repo;
    }

    /**
     * @depends testFind
     *
     * @param OrmRepository $repo
     *
     * @return OrmRepository
     */
    public function testInsert(OrmRepository $repo)
    {
        $user         = new UserEntity();
        $user->email  = 'new@qa.ru';
        $user->status = 1;
        $inserted     = $repo->insert($user);
        $this->assertEquals(true, $inserted);
        $id = $repo->lastInsertId();
        /** @var UserEntity $user */
        $user = current($repo->find(['email' => 'new@qa.ru']));
        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertEquals(1, $user->status);
        $this->assertEquals('new@qa.ru', $user->email);
        $this->assertEquals($id, $user->id);

        return $repo;
    }

    /**
     * @depends testInsert
     *
     * @param OrmRepository $repo
     *
     * @return OrmRepository
     */
    public function testUpdate(OrmRepository $repo)
    {
        /** @var UserEntity $user */
        $user = current($repo->find(['email' => 'new@qa.ru']));
        $this->assertInstanceOf(UserEntity::class, $user);
        $newStatus    = $user->status + 1;
        $user->status = $newStatus;
        $updated      = $repo->update($user);
        $this->assertEquals(true, $updated);
        $countUsers = count($repo->find(['id' => $user->id, 'status' => $newStatus]));
        $this->assertEquals(1, $countUsers);
        $this->assertEquals($repo->count(), $countUsers);

        return $repo;
    }

    /**
     * @depends testUpdate
     *
     * @param OrmRepository $repo
     */
    public function testDelete(OrmRepository $repo)
    {
        /** @var UserEntity $user */
        $user = current($repo->find(['email' => 'new@qa.ru']));
        $this->assertInstanceOf(UserEntity::class, $user);
        $userId  = $user->id;
        $deleted = $repo->delete($user);
        $this->assertEquals(true, $deleted);
        $countUsers = count($repo->find(['id' => $userId]));
        $this->assertEquals(0, $countUsers);
    }
}