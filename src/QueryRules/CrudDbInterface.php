<?php

namespace AntOrm\QueryRules;

use AntOrm\Entity\EntityWrapper;

interface CrudDbInterface
{
    const OPERATION_SELECT = 'select';
    const OPERATION_INSERT = 'insert';
    const OPERATION_UPDATE = 'update';
    const OPERATION_DELETE = 'delete';
    const OPERATION_SAVE   = 'save';
    const OPERATION_UPSERT = 'upsert';

    public function select(EntityWrapper $wrapper);

    public function insert(EntityWrapper $wrapper);

    public function update(EntityWrapper $wrapper);

    public function upsert(EntityWrapper $wrapper);

    public function delete(EntityWrapper $wrapper);
}