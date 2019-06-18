<?php

namespace DHLParcel\Shipping\Model\Config\Source;

class RateMethod implements \Magento\Framework\Option\ArrayInterface
{
    const METHOD_FLAT_RATE = 0;
    const METHOD_VARIABLE_RATE = 1;

    public function toOptionArray()
    {
        return [
            self::METHOD_FLAT_RATE     => __("Flat pricing"),
            self::METHOD_VARIABLE_RATE => __("Variable zone pricing")
        ];
    }
}
