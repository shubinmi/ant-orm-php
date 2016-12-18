<?php

namespace AntOrm\Storage\QueryRules;

interface CrudDbInterface
{

    public function select();

    public function insert();

    public function update();

    public function delete();
}