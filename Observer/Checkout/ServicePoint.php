<?php

namespace DHLParcel\Shipping\Observer\Checkout;

use DHLParcel\Shipping\Helper\Data;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class ServicePoint implements ObserverInterface
{
    protected $checkoutSession;
    protected $helper;

    public function __construct(
        CheckoutSession $checkoutSession,
        Data $helper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    public function execute(EventObserver $observer)
    {
        if (!$this->helper->getConfigData('active')) {
            return $this;
        }

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
