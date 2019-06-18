<?php

namespace DHLParcel\Shipping\Observer\Checkout;

use DHLParcel\Shipping\Model\Service\DeliveryTimes as DeliveryTimesService;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

class Sameday implements ObserverInterface
{
    protected $deliveryTimesService;

    public function __construct(
        DeliveryTimesService $deliveryTimesService
    ) {
        $this->deliveryTimesService = $deliveryTimesService;
    }

    public function execute(EventObserver $observer)
    {
        /** @var \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();

        if ($order->getShippingMethod() === 'dhlparcel_sameday') {
            $shippingAddress = $order->getShippingAddress();
            if (!$shippingAddress) {
                return $this;
            }

            if ($shippingAddress->getCountryId() !== 'NL') {
                return $this;
            }

            $this->deliveryTimesService->saveSamedaySelection($order);
        }

        return $this;
    }
}
