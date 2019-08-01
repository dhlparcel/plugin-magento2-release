<?php

namespace DHLParcel\Shipping\Model\Config\Source;

class ReferenceOptions implements \Magento\Framework\Option\ArrayInterface
{
    const OPTION_ORDER_NUMBER = 'order_number';
    const OPTION_ORDER_ID = 'order_id';
    const OPTION_ORDER_CUSTOM_FIELD = 'order_custom_field';
    const OPTION_CUSTOM_TEXT = 'custom_text';

    public function toOptionArray()
    {
        return [
            self::OPTION_ORDER_NUMBER => __('Order number'),
            self::OPTION_ORDER_ID     => __('Order ID'),
            self::OPTION_CUSTOM_TEXT  => __('Custom text'),
        ];
    }
}
