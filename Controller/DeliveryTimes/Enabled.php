<?php

namespace DHLParcel\Shipping\Controller\DeliveryTimes;

use DHLParcel\Shipping\Model\Service\DeliveryTimes as DeliveryTimesService;
use Magento\Checkout\Model\Session as CheckoutSession;

class Enabled extends \DHLParcel\Shipping\Controller\AbstractResponse
{
    protected $deliveryTimesService;
    protected $checkoutSession;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        DeliveryTimesService $deliveryTimesService,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->deliveryTimesService = $deliveryTimesService;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $isEnabled = $this->deliveryTimesService->isEnabled();
        $displayFrontend = $this->deliveryTimesService->displayFrontend();
        $notInStock = $this->deliveryTimesService->notInStock();

        $enabled = boolval($isEnabled && !$notInStock && $displayFrontend);

        return $this->resultFactory
            ->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->setData([
                'status'  => 'success',
                'data'    => $enabled,
                'message' => null
            ]);
    }
}
