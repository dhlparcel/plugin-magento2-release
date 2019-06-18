<?php

namespace DHLParcel\Shipping\Model\Config\Source;

class BulkNotification implements \Magento\Framework\Option\ArrayInterface
{
    const NOTIFICATION_SILENCED = 'notification_silenced';
    const NOTIFICATION_STACKED = 'notification_stacked';
    const NOTIFICATION_SINGLE = 'notification_single';
    const NOTIFICATION_COMBINED = 'notification_combined';

    const UN_CATEGORIZED_EXCEPTION_CODE = 'other';
    const COMBINED_EXCEPTION_CODE = 'combined';

    public function toOptionArray()
    {
        return [
            self::NOTIFICATION_STACKED  => __('Display errors, orders stacked per error'),
            self::NOTIFICATION_SINGLE   => __('Display errors, individually per order'),
            self::NOTIFICATION_COMBINED => __('Hide all errors, but list order numbers'),
            self::NOTIFICATION_SILENCED => __('Hide all errors'),
        ];
    }
}
