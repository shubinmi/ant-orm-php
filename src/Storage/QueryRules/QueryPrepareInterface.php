<?php

namespace AntOrm\Storage\QueryRules;

use AntOrm\Entity\EntityProperty;

interface QueryPrepareInterface
{
    /**
     * @param string           $operation
     * @param EntityProperty[] $properties
     *
     * @return array
     */
    public function prepare($operation, array $properties);
}