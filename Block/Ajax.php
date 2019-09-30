<?php

namespace DHLParcel\Shipping\Block;

use DHLParcel\Shipping\Helper\Data;

class Ajax extends \Magento\Framework\View\Element\Template
{
    protected $helper;
    /**
     * Ajax constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(\Magento\Framework\View\Element\Template\Context $context, Data $helper, array $data = [])
    {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    public function getGoogleMapsKey()
    {
        return $this->helper->getConfigData('shipping_methods/servicepoint/google_maps_api_key');
    }
}
