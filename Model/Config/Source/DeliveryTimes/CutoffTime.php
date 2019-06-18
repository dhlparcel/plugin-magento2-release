<?php

namespace DHLParcel\Shipping\Model\Config\Source\DeliveryTimes;

class CutoffTime implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        $ceil = 24;
        $hours = range(1, $ceil);

        $list = array();
        foreach ($hours as $hour) {
            if ($hour === 24) {
                $list[$hour] = '23:59';
            } else {
                $list[$hour] = sprintf('%s:00', $hour);
            }
        }

        return $list;
    }
}
