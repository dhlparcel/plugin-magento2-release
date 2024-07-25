<?php

namespace DHLParcel\Shipping\Observer\Checkout;

use DHLParcel\Shipping\Model\Service\DeliveryTimes as DeliveryTimesService;
use DHLParcel\Shipping\Helper\Data;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

class Sameday implements ObserverInterface
{
    protected $deliveryTimesService;
    protected $helper;

    public function __construct(
        DeliveryTimesService $deliveryTimesService,
        Data $helper
    ) {
        $this->deliveryTimesService = $deliveryTimesService;
        $this->helper = $helper;
    }

    public function execute(EventObserver $observer)
    {
        if (!$this->helper->getConfigData('active')) {
            return $this;
        }

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
