<?php

namespace DHLParcel\Shipping\Controller\Adminhtml\Shipment;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Service\Capability as CapabilityService;

class Capabilities extends \Magento\Backend\App\Action
{
    protected $capabilityService;
    protected $helper;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        CapabilityService $capabilityService,
        Data $helper
    ) {
        $this->capabilityService = $capabilityService;
        $this->helper = $helper;

        parent::__construct($context);
    }

    public function execute()
    {
        $toCountry = $this->getRequest()->getParam('country');
        $toPostalCode = $this->getRequest()->getParam('postalcode');
        $toBusiness = $this->getRequest()->getParam('audience') == 'business';
        $storeId = $this->getRequest()->getParam('store_id');

        $options = $this->capabilityService->getOptions($storeId, $toCountry, $toPostalCode, $toBusiness);
        $sizes = $this->capabilityService->getSizes($storeId, $toCountry, $toPostalCode, $toBusiness);
        $sizes = $this->capabilityService->setDisplayWeight($sizes);
        $this->setDefaultSize($sizes);

        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        if (empty($options) || empty($sizes)) {
            $resultJson->setHttpResponseCode(400)->setData(['error' => 'something went wrong with getting the data']);
            return $resultJson;
        }

        $resultJson->setData([
            'options' => $options,
            'products' => $sizes,
        ]);
        return $resultJson;
    }

    protected function setDefaultSize(&$sizes)
    {
        $ignoredSizes = explode(',', $this->helper->getConfigData('label/ignored_sizes') ?? '');
        foreach ($sizes as &$size) {
            if (!in_array($size['key'], $ignoredSizes)) {
                $size['selected'] = true;

                return;
            }
        }
    }
}
