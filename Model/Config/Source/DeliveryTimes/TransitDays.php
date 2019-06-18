<?php

namespace DHLParcel\Shipping\Model\Config\Source\DeliveryTimes;

class TransitDays implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        $days = [];
        $maxDays = 14;

        for ($i = 1; $i <= $maxDays; $i++) {
            if ($i == 1) {
                $days[$i] = sprintf(__('Delivered the next day'), $i);
            } else {
                $days[$i] = sprintf(__('Delivered in %s days'), $i);
            }
        }

        return $days;
    }
}
