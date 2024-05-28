<?php

namespace DHLParcel\Shipping\Observer\Checkout;

use DHLParcel\Shipping\Model\Service\DeliveryServices as DeliveryServicesService;
use DHLParcel\Shipping\Helper\Data;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

use Magento\Checkout\Model\Session as CheckoutSession;

class DeliveryServices implements ObserverInterface
{
    protected $deliveryServicesService;
    protected $checkoutSession;
    protected $helper;

    public function __construct(
        DeliveryServicesService $deliveryServicesService,
        CheckoutSession $checkoutSession,
        Data $helper
    ) {
        $this->deliveryServicesService = $deliveryServicesService;
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    public function execute(EventObserver $observer)
    {
        if (!$this->helper->getConfigData('active')) {
            return $this;
        }

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
