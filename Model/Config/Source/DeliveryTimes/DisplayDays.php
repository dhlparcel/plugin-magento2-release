<?php

namespace DHLParcel\Shipping\Model\Config\Source\DeliveryTimes;

class DisplayDays implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        $days = [];
        $maxDays = 14;

        for ($i = 1; $i <= $maxDays; $i++) {
            if ($i == 1) {
                $days[$i] = sprintf(__('Show up to %s day ahead'), $i);
            } else {
                $days[$i] = sprintf(__('Show up to %s days ahead'), $i);
            }
        }

        return $days;
    }
}
