<?php

namespace DHLParcel\Shipping\Model\Config\Source;

class ShipmentInsurance implements \Magento\Framework\Option\ArrayInterface
{
    const OPTION_NONE = '';
    const OPTION_500 = '500';
    const OPTION_1000 = '1000';
    const OPTION_CUSTOM = 'custom';

    public function toOptionArray()
    {
        return [
            self::OPTION_NONE   => __('None'),
            self::OPTION_500    => __('up to € 500'),
            self::OPTION_1000   => __('between € 500 and € 1000'),
            self::OPTION_CUSTOM => __('More than € 1000'),
        ];
    }
}
