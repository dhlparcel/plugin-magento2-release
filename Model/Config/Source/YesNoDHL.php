<?php

namespace DHLParcel\Shipping\Model\Config\Source;

class YesNoDHL implements \Magento\Framework\Option\ArrayInterface
{
    const OPTION_YES = 1;
    const OPTION_NO = 0;
    const OPTION_DHL = 2;

    public function toOptionArray()
    {
        return [
            self::OPTION_NO => __('No'),
            self::OPTION_YES => __('Yes'),
            self::OPTION_DHL => __('Yes, but only for orders with DHL shipping methods'),
        ];
    }
}
