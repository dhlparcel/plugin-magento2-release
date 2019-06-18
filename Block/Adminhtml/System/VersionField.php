<?php

namespace DHLParcel\Shipping\Block\Adminhtml\System;

use Magento\Framework\Module\ModuleListInterface;

class VersionField extends \Magento\Framework\Data\Form\Element\AbstractElement
{
    protected $moduleList;

    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->moduleList = $moduleList;
    }

    public function getElementHtml()
    {
        return $this->moduleList->getOne('DHLParcel_Shipping')['setup_version'];
    }
}
