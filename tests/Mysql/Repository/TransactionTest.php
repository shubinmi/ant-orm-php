<?php

namespace AntOrm\Tests\Mysql;

use AntOrm\Repository\OrmRepository;
use AntOrm\Storage\OrmStorage;
use AntOrm\Tests\Entities\UserEntity;
use PHPUnit\Framework\TestCase;

class TransactionTest extends TestCase
{
    //public function testCommit()
    //{
    //    /**
    //     * @var OrmStorage $storage
    //     * @var UserEntity $user
    //     */
    //    global $storage;
    //    $repoForTransaction = new OrmRepository(clone $storage, UserEntity::className());
    //    $repoForChecking    = clone $repoForTransaction;
    //    $email              = 'transaction@qa.io';
    //    $user               = current($repoForChecking->find());
    //
    //    $this->assertInstanceOf(UserEntity::class, $user);
    //    $oldStatus    = $user->status;
    //    $changeUserId = $user->id;
    //
    //    $r = $repoForChecking->find(['email' => $email]);
    //    $this->assertCount(0, $r);
    //
    //    $onTransaction = $repoForTransaction->startTransaction();
    //    $this->assertEquals(true, $onTransaction);
    //    $newUser         = new UserEntity();
    //    $newUser->email  = $email;
    //    $newUser->status = 5;
    //    $repoForTransaction->insert($newUser);
    //    $user->status = $oldStatus + 1;
    //    $repoForTransaction->update($user);
    //    $user = current($repoForChecking->find(['id' => $changeUserId]));
    //    $this->assertEquals($oldStatus, $user->status);
    //    $r = $repoForChecking->find(['email' => $email]);
    //    $this->assertCount(0, $r);
    //    $r = $repoForTransaction->endTransaction();
    //    $this->assertEquals(true, $r);
    //    $newUser->id = $repoForTransaction->lastInsertId();
    //
    //    $user = current($repoForChecking->find(['id' => $changeUserId]));
    //    $this->assertEquals($oldStatus + 1, $user->status);
    //    $r = $repoForChecking->find(['email' => $email]);
    //    $this->assertCount(1, $r);
    //
    //    $r = $repoForTransaction->delete($newUser);
    //    $this->assertEquals(true, $r);
    //    $user->status = $oldStatus;
    //    $r            = $repoForTransaction->update($user);
    //    $this->assertEquals(true, $r);
    //}

    public function testRollback()
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
        $oldStatus          = $user->status;
        $changeUserId       = $user->id;

        $onTransaction = $repoForTransaction->startTransaction();
        $this->assertEquals(true, $onTransaction);
        $newUser             = new UserEntity();
        $newUser->email      = $email;
        $newUser->status     = 5;
        $invalidUser         = new UserEntity();
        $invalidUser->id     = $changeUserId;
        $invalidUser->status = 3;
        $invalidUser->email  = 'error@qa.io';
        $repoForTransaction->insert($newUser);
        $user->status = $oldStatus + 1;
        $repoForTransaction->update($user);
        $repoForTransaction->insert($invalidUser);
        try {
            $repoForTransaction->endTransaction();
        } catch (\Exception $e) {
            $this->assertContains('Query error', $e->getMessage());
        }
        $r = $repoForChecking->find(['email in' => "('error@qa.io', '{$email}')"]);
        $this->assertCount(0, $r);
        $user = current($repoForChecking->find());
        $this->assertEquals($oldStatus, $user->status);
    }
}