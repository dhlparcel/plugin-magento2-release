<?php

namespace DHLParcel\Shipping\Observer\Checkout;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class ServicePoint implements ObserverInterface
{
    protected $checkoutSession;

    public function __construct(
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    public function execute(EventObserver $observer)
    {
        $order = $observer->getOrder();
        if ($order->getShippingMethod() === 'dhlparcel_servicepoint') {
            // Save session ServicePoint to order
            $servicePointId = $this->checkoutSession->getDHLParcelShippingServicePointId();
            if ($servicePointId) {
                $order->setData('dhlparcel_shipping_servicepoint_id', $servicePointId);
            }
        }
        return $this;
    }
}
