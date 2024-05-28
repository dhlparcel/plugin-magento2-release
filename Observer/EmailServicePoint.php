<?php

namespace DHLParcel\Shipping\Observer;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Service\ServicePoint as ServicePointService;

class EmailServicePoint implements \Magento\Framework\Event\ObserverInterface
{
    protected $helper;
    protected $servicePointService;

    public function __construct(
        Data $helper,
        ServicePointService $servicePointService
    ) {
        $this->helper = $helper;
        $this->servicePointService = $servicePointService;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->helper->getConfigData('active')) {
            return;
        }

        /** @var \Magento\Framework\DataObject $transport */
        $transport = $observer->getData('transport');
        if (is_array($transport)) {
            $transport = $observer->getData('transportObject');
        }

        if (!isset($transport)) {
            return;
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $transport->getData('order');

        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billingAddress = $transport->getData('billing');

        if (!isset($order) || !isset($billingAddress)) {
            return;
        }

        // Check if order or invoice
        if (empty($transport->getData('invoice'))) {
            if (!boolval($this->helper->getConfigData('usability/template_overwrites/email_order_servicepoint'))) {
                return;
            }
        } else {
            if (!boolval($this->helper->getConfigData('usability/template_overwrites/email_invoice_servicepoint'))) {
                return;
            }
        }

        if ($servicePointId = $order->getData('dhlparcel_shipping_servicepoint_id')) {
            $servicePoint = $this->servicePointService->get($servicePointId, $billingAddress->getCountryId());

            if (!isset($servicePoint)) {
                return;
            }

            $order->setShippingDescription(implode(', ', [
                __('DHL ServicePoint:') . ' ' . $servicePoint->name,
                trim($servicePoint->address->street . ' ' . $servicePoint->address->number . ' ' . $servicePoint->address->addition),
                $servicePoint->address->postalCode . ' ' . $servicePoint->address->city
            ]));
        }
    }
}
