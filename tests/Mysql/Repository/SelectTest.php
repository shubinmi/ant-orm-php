<?php

namespace AntOrm\Tests\Mysql;

use AntOrm\Repository\OrmRepository;
use AntOrm\Storage\OrmStorage;
use AntOrm\Tests\Entities\UserEntity;
use PHPUnit\Framework\TestCase;

class SelectTest extends TestCase
{
    /**
     * @return OrmRepository
     */
    public function testFind()
    {
        /** @var OrmStorage $storage */
        global $storage;
        $repo     = new OrmRepository(clone $storage);
        $selected = $repo->select(
            UserEntity::className(),
            [
                'where'     => [
                    'AND' => [
                        'status >' => 0,
                        'id <='    => 3,
                        'OR'       => [
                            'LOWER(p.last_name) LIKE' => 'qa',
                            'AND'                     => [
                                'p.first_name' => 'Ant',
                                'p.last_name'  => 'Orm',
                            ],
                        ],
                        'a.id IS'  => 'NULL',
                    ]
                ],
                'join'      => [
                    'profiles as p ON p.user_id = users.id',
                ],
                'left join' => [
                    [
                        'table' => 'addresses as a',
                        'on'    => 'a.user_id=users.id'
                    ],
                ],
                'order-by'  => 'users.id DESC',
                'limit'     => 1,
                'offset'    => 1
            ]
        );
        $this->assertEquals(true, $selected);
        $result = $repo->result();
        $this->assertCount(1, $result);
        $user = new UserEntity(current($result));
        $this->assertEquals(2, $user->id);
        $this->assertEquals(2, $user->status);
        $this->assertEquals('alex@qa.io', $user->email);

        return $repo;
    }
}