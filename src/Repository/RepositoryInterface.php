<?php

namespace AntOrm\Repository;

interface RepositoryInterface
{
    public function select($entityClass, $searchParams);
    public function insert($entity);
    public function update($entity);
    public function delete($entity);
}