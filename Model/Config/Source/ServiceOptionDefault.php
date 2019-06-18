<?php

namespace DHLParcel\Shipping\Model\Config\Source;

class ServiceOptionDefault implements \Magento\Framework\Option\ArrayInterface
{
    const OPTION_INACTIVE = 'inactive';
    const OPTION_IF_AVAILABLE = 'if_available';
    const OPTION_SKIP_NOT_AVAILABLE = 'skip_not_available';

    public function toOptionArray()
    {
        return [
            self::OPTION_INACTIVE           => __('No'),
            self::OPTION_IF_AVAILABLE       => __('Yes (and for bulk label creation: enable if available)'),
            self::OPTION_SKIP_NOT_AVAILABLE => __('Yes (and for bulk label creation: must be available)'),
        ];
    }
}
