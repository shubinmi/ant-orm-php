<?php

namespace AntOrm\Repository;

interface RepositoryInterface
{
    /**
     * @param       $entityClass
     * @param array $searchParams
     *
     * @return mixed
     */
    public function select($entityClass, $searchParams);

    /**
     * @param $entity
     *
     * @return bool
     */
    public function insert($entity);

    /**
     * @param $entity
     *
     * @return bool
     */
    public function update($entity);

    /**
     * @param $entity
     *
     * @return bool
     */
    public function delete($entity);
}