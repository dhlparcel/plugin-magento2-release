<?php

namespace DHLParcel\Shipping\Controller\DeliveryTimes;

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
        $identifier = $data->identifier ?: null;
        $date = $data->date ?: null;
        $startTime = $data->startTime ?: null;
        $endTime = $data->endTime ?: null;

        $this->checkoutSession->setDHLParcelShippingDeliveryTimesIdentifier($identifier);
        $this->checkoutSession->setDHLParcelShippingDeliveryTimesDate($date);
        $this->checkoutSession->setDHLParcelShippingDeliveryTimesStartTime($startTime);
        $this->checkoutSession->setDHLParcelShippingDeliveryTimesEndTime($endTime);

        $validated = boolval(!empty($identifier) && !empty($date) && !empty($startTime) && !empty($endTime) && ($identifier !== 'sameday'));

        return $this->resultFactory
            ->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->setData([
                'status'  => 'success',
                'data'    => $validated,
                'message' => null
            ]);
    }
}
