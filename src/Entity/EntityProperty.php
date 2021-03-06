<?php

namespace AntOrm\Entity;

use AntOrm\Entity\Helpers\AnnotationParser;
use AntOrm\Entity\Objects\OrmProperty;

class EntityProperty
{
    const BIND_TYPE_INTEGER = 'i';
    const BIND_TYPE_STRING  = 's';
    const BIND_TYPE_DOUBLE  = 'd';
    const BIND_TYPE_BLOB    = 'b';

    /**
     * @var string
     */
    public $name = '';

    /**
     * @var string
     */
    public $value = '';

    /**
     * @var string
     */
    public $doc = '';

    /**
     * @var OrmProperty
     */
    public $metaData;

    /**
     * @var EntityWrapper
     */
    public $parentEntityWrapper;

    /**
     * @param string $name
     * @param string $value
     * @param string $doc
     */
    public function __construct($name, $value, $doc)
    {
        $this->name     = $name;
        $this->value    = $value;
        $this->doc      = AnnotationParser::prepareDocBlock($doc);
        $this->metaData = new OrmProperty();
    }

    /**
     * @return string
     */
    public function getBindTypePattern()
    {
        if (empty($this->doc)) {
            return self::BIND_TYPE_STRING;
        }
        $doc = strtolower(str_replace([' ', "\r\n", "\n", "\r"], '', $this->doc));
        if ($ormDoc = AnnotationParser::getBindTypeByOrmAnnotation($doc)) {
            return $ormDoc;
        }
        if ($varDoc = AnnotationParser::getBindTypeByVarDoc($doc)) {
            return $varDoc;
        }

        return self::BIND_TYPE_STRING;
    }
}