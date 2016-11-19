<?php

namespace AntOrm\Storage\QueryRules;

use AntOrm\Entity\EntityProperty;

interface QueryRuleInterface
{
    /**
     * @param string           $operation
     * @param EntityProperty[] $properties
     *
     * @return array
     */
    public function prepare($operation, array $properties);
}