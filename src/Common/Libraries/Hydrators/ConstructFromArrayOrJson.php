<?php

namespace AntOrm\Common\Libraries\Hydrators;

abstract class ConstructFromArrayOrJson
{
    /**
     * @param string(json)|array|\stdClass $params
     */
    public function __construct($params = null)
    {
        $params = $this->convertToArray($params);
        if (empty($params)) {
            return;
        }
        foreach ($params as $property => $value) {
            $setter = 'set' . str_replace('_', '', $property);
            if (method_exists($this, $setter)) {
                $this->{$setter}($value);
            } elseif (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }

    /**
     * @param mixed $params
     *
     * @return array
     */
    protected function convertToArray($params)
    {
        if (is_string($params)) {
            $params = json_decode($params, true);
        } elseif ($params instanceof \stdClass) {
            $params = (array)$params;
        }
        if (!is_array($params)) {
            return [];
        }

        return $params;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $result = [];
        foreach (get_object_vars($this) as $property => $value) {
            $getter = 'get' . str_replace('_', '', $property);
            if (method_exists($this, $getter)) {
                $result[$property] = $this->{$getter}();
            } else {
                $result[$property] = $this->{$property};
            }
        }

        return array_filter(
            $result,
            function ($value) {
                return isset($value);
            }
        );
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }
}