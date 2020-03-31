<?php

namespace DHLParcel\Shipping\Block\Adminhtml\System;

use DHLParcel\Shipping\Helper\Data;

class AutoPrintWarning extends \Magento\Framework\Data\Form\Element\AbstractElement
{
    protected $helper;

    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->helper = $helper;
    }

    public function getElementHtml()
    {
        $message = __('Automatically created DHL labels can be immediately sent to printer. Please setup and enable Print Service to use this feature');

        return $message;
    }
}
