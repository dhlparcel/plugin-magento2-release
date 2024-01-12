<?php

namespace DHLParcel\Shipping\Model\Config\Source;

class YesNoTest implements \Magento\Framework\Option\ArrayInterface
{
    const OPTION_YES = 1;
    const OPTION_TEST = 2;
    const OPTION_NO = 0;

    public function toOptionArray()
    {
        return [
            self::OPTION_YES => __('Yes'),
            self::OPTION_TEST => __('Test mode'),
            self::OPTION_NO => __('No'),
        ];
    }
}
