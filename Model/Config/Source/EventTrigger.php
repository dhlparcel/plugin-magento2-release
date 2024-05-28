<?php

namespace DHLParcel\Shipping\Model\Config\Source;

class EventTrigger implements \Magento\Framework\Option\ArrayInterface
{
    const TRIGGER_SAVE_AFTER = 'sales_order_save_after';
    const TRIGGER_COMMIT_AFTER = 'sales_order_save_commit_after';

    public function toOptionArray()
    {
        return [
            self::TRIGGER_SAVE_AFTER   => 'sales_order_save_after',
            self::TRIGGER_COMMIT_AFTER => 'sales_order_save_commit_after'
        ];
    }
}
