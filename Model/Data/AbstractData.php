<?php

namespace DHLParcel\Shipping\Model\Data;

abstract class AbstractData
{
    private $objectManager;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectmanager,
        $automap = []
    ) {
        $this->objectManager = $objectmanager;
        $this->setArray($automap);
    }

    public function setArray($array = [])
    {
        if (!is_array($array) || empty($array)) {
            return;
        }

        $me = $this;
        $properties = function () use ($me) {
            return get_object_vars($me);
        };

        $classMap = $this->getClassMap();
        $classArrayMap = $this->getClassArrayMap();

        foreach ($properties() as $key => $value) {
            if (array_key_exists($key, $array)) {
                if (is_array($array[$key])) {
                    if (array_key_exists($key, $classArrayMap)) {
                        // Class array mapper
                        $class = $classArrayMap[$key];
                        foreach ($array[$key] as $entry) {
                            if ($entry instanceof AbstractData) {
                                $this->{$key}[] = $entry;
                            } else {
                                $this->{$key}[] = $this->objectManager->create($class, ['automap' => $entry]);
                            }
                        }
                    } elseif (array_key_exists($key, $classMap)) {
                        // Class mapper
                        $class = $classMap[$key];
                        $this->$key = $this->objectManager->create($class, ['automap' => $array[$key]]);
                    } else {
                        // Simple stdObject mapper
                        $this->$key = $this->convertToNestedObjects($array[$key]);
                    }
                } else {
                    $this->$key = $array[$key];
                }
            }
        }
    }

    public function toJSON($removeNulls = false)
    {
        $json = json_encode($this);
        if ($removeNulls) {
            $json = $this->removeNulls($json);
        }
        return $json;
    }

    public function toArray($removeNulls = false)
    {
        $json = $this->toJSON($removeNulls);
        return json_decode($json, true);
    }

    protected function removeNulls($json)
    {
        return preg_replace('/,\s*"[^"]+": ?null|"[^"]+": ?null,?/', '', $json);
    }

    protected function convertToNestedObjects($array)
    {
        if ($this->isAssociative($array)) {
            return (object)$array;
        }

        $stack = [];
        foreach ($array as $value) {
            if (is_array($value)) {
                $stack[] = $this->convertToNestedObjects($value);
            } else {
                // TODO handle errors, but it should not come here.
                // This would be a random field with no key, which makes no sense for JSON data.
            }
        }

        return $stack;
    }

    protected function isAssociative($array)
    {
        if ([] === $array) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function getClassMap()
    {
        return [];
    }

    protected function getClassArrayMap()
    {
        return [];
    }
}
