<?php

namespace AntOrm\Entity;

class EntityProperty
{
    const TYPE_INTEGER = 'i';
    const TYPE_STRING  = 's';
    const TYPE_DOUBLE  = 'd';

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

    /**
     * @return string
     */
    public function getTypePatternByDoc()
    {
        if (empty($this->doc)) {
            return self::TYPE_STRING;
        }
        $doc = strtolower(str_replace([' ', "\r\n", "\n", "\r"], '', $this->doc));
        $doc = explode('@var', $doc);
        if (empty($doc[1])) {
            return self::TYPE_STRING;
        }
        $doc  = explode('*', $doc[1]);
        if (empty($doc[0])) {
            return self::TYPE_STRING;
        }
        if (strpos($doc[0], 'int') !== false) {
            return self::TYPE_INTEGER;
        } elseif (strpos($doc[0], 'float') !== false) {
            return self::TYPE_DOUBLE;
        } elseif (strpos($doc[0], 'bool') !== false) {
            return self::TYPE_INTEGER;
        } elseif (strpos($doc[0], 'double') !== false) {
            return self::TYPE_DOUBLE;
        }

        return self::TYPE_STRING;
    }
}