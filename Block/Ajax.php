<?php

namespace DHLParcel\Shipping\Block;

use DHLParcel\Shipping\Helper\Data;

class Ajax extends \Magento\Framework\View\Element\Template
{
    protected $helper;
    protected $store;

    /**
     * Ajax constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Locale\Resolver $store,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->store = $store;
    }

    public function getGoogleMapsKey()
    {
        return $this->helper->getConfigData('shipping_methods/servicepoint/google_maps_api_key');
    }

    public function getLanguage()
    {
        $locale = $this->store->getLocale();
        $localeParts = explode('_', $locale);
        return strtolower(reset($localeParts));
    }
}
