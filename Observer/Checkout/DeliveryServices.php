<?php

namespace DHLParcel\Shipping\Observer\Checkout;

use DHLParcel\Shipping\Model\Service\DeliveryServices as DeliveryServicesService;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class DeliveryServices implements ObserverInterface
{
    protected $deliveryServicesService;
    protected $checkoutSession;

    public function __construct(
        DeliveryServicesService $deliveryServicesService,
        CheckoutSession $checkoutSession
    ) {
        $this->deliveryServicesService = $deliveryServicesService;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute(EventObserver $observer)
    {
        /** @var \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();

        $deliveryServices = $this->checkoutSession->getDHLParcelShippingDeliveryServices();
        $deliveryServices = $this->deliveryServicesService->filterAllowedOnly($deliveryServices);
        if (empty($deliveryServices)) {
            return $this;
        }

        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress) {
            return $this;
        }

        $shippingMethod = $order->getShippingMethod();
        if ($shippingMethod !== 'dhlparcel_door') {
            return $this;
        }

        $this->deliveryServicesService->saveSelection($order, $deliveryServices);

        return $this;
    }
}
