<?php

namespace DHLParcel\Shipping\Controller\Adminhtml\Shipment;

use DHLParcel\Shipping\Model\Service\Capability as CapabilityService;

class Capabilities extends \Magento\Backend\App\Action
{
    protected $capabilityService;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        CapabilityService $capabilityService
    ) {
        $this->capabilityService = $capabilityService;

        parent::__construct($context);
    }

    public function execute()
    {
        $toCountry = $this->getRequest()->getParam('country');
        $toPostalCode = $this->getRequest()->getParam('postalcode');
        $toBusiness = $this->getRequest()->getParam('audience') == 'business';

        $options = $this->capabilityService->getOptions($toCountry, $toPostalCode, $toBusiness);
        $sizes = $this->capabilityService->getSizes($toCountry, $toPostalCode, $toBusiness);

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
}
