<?php

namespace AntOrm\Entity;

class EntityProperty
{
    public $name = '';
    public $value = '';
    public $doc = '';

    /**
     * @param string $name
     * @param string $value
     * @param string $doc
     */
    public function __construct($name, $value, $doc)
    {
        $this->name  = $name;
        $this->value = $value;
        $this->doc   = $doc;
    }

    public function getTypePatternByDoc()
    {
        if (empty($this->doc)) {
            return 's';
        }
        $doc  = strtolower(str_replace([' ', "\r\n", "\n", "\r"], '', $this->doc));
        $doc  = explode('@var', $doc);
        $type = !empty($doc[1][0]) ? strtolower($doc[1][0]) : 's';
        $type = $type == 'b' ? 'i' : $type;

        return in_array($type, ['i', 's', 'd']) ? $type : 's';
    }
}