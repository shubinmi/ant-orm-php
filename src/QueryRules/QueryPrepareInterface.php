<?php

namespace AntOrm\QueryRules;

use AntOrm\Entity\EntityWrapper;

interface QueryPrepareInterface
{
    /**
     * @param string        $operation
     * @param EntityWrapper $wrapper
     *
     * @return QueryStructure
     */
    public function prepare($operation, EntityWrapper $wrapper);
}