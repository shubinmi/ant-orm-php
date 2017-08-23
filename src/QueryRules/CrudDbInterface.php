<?php

namespace AntOrm\QueryRules;

use AntOrm\Entity\EntityWrapper;

interface CrudDbInterface
{
    public function select(EntityWrapper $wrapper);

    public function insert(EntityWrapper $wrapper);

    public function update(EntityWrapper $wrapper);

    public function upsert(EntityWrapper $wrapper);

    public function save(EntityWrapper $wrapper);

    public function delete(EntityWrapper $wrapper);
}