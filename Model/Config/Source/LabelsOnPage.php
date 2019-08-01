<?php

namespace DHLParcel\Shipping\Model\Config\Source;

class LabelsOnPage implements \Magento\Framework\Option\ArrayInterface
{
    const LABEL_PAGE_DEFAULT = 1;
    const LABEL_PAGE_TRIPLE = 3;
    const LABEL_PAGE_QUADRUPLE = 4;

    public function toOptionArray()
    {
        return [
            self::LABEL_PAGE_DEFAULT => __("Default (1 label per page)"),
            self::LABEL_PAGE_TRIPLE  => __("Print 3 labels per A4 sheet")
        ];
    }
}
