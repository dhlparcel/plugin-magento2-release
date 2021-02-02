<?php

namespace DHLParcel\Shipping\Controller\DeliveryServices;

use DHLParcel\Shipping\Model\Service\Capability as CapabilityService;
use DHLParcel\Shipping\Model\Service\DeliveryServices as DeliveryServicesService;

use Magento\Checkout\Model\Session as CheckoutSession;

class Available extends \DHLParcel\Shipping\Controller\AbstractResponse
{
    protected $storeManager;
    protected $capabilityService;
    protected $deliveryServicesService;
    protected $checkoutSession;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        CapabilityService $capabilityService,
        DeliveryServicesService $deliveryServicesService,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->capabilityService = $capabilityService;
        $this->deliveryServicesService = $deliveryServicesService;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $data = $this->getRequest()->getPost();
        $toBusiness = $this->deliveryServicesService->getToBusiness();
        $toPostalCode = $data->postcode ?: null;
        $toCountry = $data->country ?: null;
        $subtotal = $this->checkoutSession->getQuote()->getSubtotal();

        $selections = $this->checkoutSession->getDHLParcelShippingDeliveryServices();
        $selections = $this->deliveryServicesService->filterAllowedOnly($selections);

        $options = [];
        if (count($this->deliveryServicesService->getAllowedOptions()) > 0) {
            $options = $this->capabilityService->getOptions($this->storeManager->getStore()->getId(), $toCountry, $toPostalCode, $toBusiness, ['DOOR']);
        }

        $data = $this->deliveryServicesService->getAvailability($options, $subtotal, $selections, $this->storeManager->getStore()->getId());

        return $this->resultFactory
            ->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->setData([
                'status'  => 'success',
                'data'    => $data,
                'message' => null
            ]);
    }
}
