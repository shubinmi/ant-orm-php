<?php

namespace AntOrm\Storage\Singleton;

use AntOrm\Entity\OrmEntity;

class QueriesQueueForDeleteStorage
{
    /**
     * @var string[]
     */
    private static $list = [];

    public static function clear()
    {
        self::$list = [];
    }

    /**
     * @param OrmEntity $entity
     */
    public static function add(OrmEntity $entity)
    {
        self::$list[] = $entity::className();
    }

    /**
     * @param OrmEntity $entity
     *
     * @return bool
     */
    public static function has(OrmEntity $entity)
    {
        return in_array($entity::className(), self::$list);
    }

    /**
     * @param OrmEntity $entity
     *
     * @return bool
     */
    public static function itFirst(OrmEntity $entity)
    {
        return $entity::className() == self::$list[0];
    }
}