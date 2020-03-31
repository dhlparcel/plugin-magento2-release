<?php

namespace DHLParcel\Shipping\Controller\DeliveryServices;

use DHLParcel\Shipping\Model\Service\DeliveryTimes as DeliveryTimesService;
use Magento\Checkout\Model\Session as CheckoutSession;

class Sync extends \DHLParcel\Shipping\Controller\AbstractResponse
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
        $data = $this->getRequest()->getPost();
        $services = isset($data->services) && is_array($data->services) ? $data->services : [];
        $sequence = isset($data->sequence) && is_numeric($data->sequence) ? intval($data->sequence) : 0;

        $this->checkoutSession->setDHLParcelShippingDeliveryServices($services);

        return $this->resultFactory
            ->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->setData([
                'status'  => 'success',
                'data'    => [
                    'sequence' => $sequence,
                ],
                'message' => null
            ]);
    }
}
