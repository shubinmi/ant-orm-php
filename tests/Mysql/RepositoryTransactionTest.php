<?php

namespace AntOrm\Tests\Mysql;

use AntOrm\Repository\OrmRepository;
use AntOrm\Storage\OrmStorage;
use PHPUnit\Framework\TestCase;

class RepositoryTransactionTest extends TestCase
{
    public function testTransactionCommit()
    {
        /**
         * @var OrmStorage $storage
         * @var UserEntity $user
         */
        global $storage;
        $repoForTransaction = new OrmRepository(clone $storage, UserEntity::className());
        $repoForChecking    = clone $repoForTransaction;
        $email              = 'transaction@qa.io';
        $user               = current($repoForChecking->find());

        $this->assertInstanceOf(UserEntity::class, $user);
        $oldStatus    = $user->status;
        $changeUserId = $user->id;

        $r = $repoForChecking->find(['email' => $email]);
        $this->assertCount(0, $r);

        $onTransaction = $repoForTransaction->startTransaction();
        $this->assertEquals(true, $onTransaction);
        $newUser         = new UserEntity();
        $newUser->email  = $email;
        $newUser->status = 5;
        $repoForTransaction->insert($newUser);
        $user->status = $oldStatus + 1;
        $repoForTransaction->update($user);
        $user = current($repoForChecking->find(['id' => $changeUserId]));
        $this->assertEquals($oldStatus, $user->status);
        $r = $repoForChecking->find(['email' => $email]);
        $this->assertCount(0, $r);
        $r = $repoForTransaction->endTransaction();
        $this->assertEquals(true, $r);

        $user = current($repoForChecking->find(['id' => $changeUserId]));
        $this->assertEquals($oldStatus + 1, $user->status);
        $r = $repoForChecking->find(['email' => $email]);
        $this->assertCount(1, $r);
    }
}