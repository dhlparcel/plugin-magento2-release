<?php

namespace DHLParcel\Shipping\Block\Adminhtml\System;

use DHLParcel\Shipping\Helper\Data;

class DirectPrintWarning extends \Magento\Framework\Data\Form\Element\AbstractElement
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
        if ($this->helper->getConfigData('usability/bulk/print')) {
            $message = __('Bulk action print is active, no action is necessary');
        } else {
            $message = __('Print service has been activated. However to use print in bulk actions it must first be enabled. This can be found under Usability - Bulk Operations - Print labels');
        }
        return $message;
    }
}
