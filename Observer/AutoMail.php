<?php

namespace DHLParcel\Shipping\Observer;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Service\Label as LabelService;
use Magento\Framework\Exception\LocalizedException;

use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Framework\App\RequestInterface;

class AutoMail implements \Magento\Framework\Event\ObserverInterface
{
    protected $helper;
    protected $labelService;
    protected $shipmentSender;
    protected $request;

    public function __construct(
        ShipmentSender $shipmentSender,
        Data $helper,
        LabelService $labelService,
        RequestInterface $request
    ) {
        $this->helper = $helper;
        $this->labelService = $labelService;
        $this->shipmentSender = $shipmentSender;
        $this->request = $request;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // Don't do anything when auto mail is disabled.
        if (boolval($this->helper->getConfigData('usability/automation/mail')) === false) {
            return;
        }

        /* @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        if (!$order->hasShipments()) {
            return;
        }

        foreach ($order->getShipmentsCollection() as $shipment) {
            if ($shipment->getEmailSent() || $shipment->getSendEmail()) {
                continue;
            }

            // Check if there are DHL eCommerce labels
            try {
                $labelIds = $this->labelService->getShipmentLabelIds($shipment);
            } catch (LocalizedException $e) {
                $labelIds = null;
            }

            if (empty($labelIds)) {
                continue;
            }

            if (!empty($this->request->getParam('shipment')['send_email'])) {
                return;
            }

            try {
                /* @var \Magento\Sales\Model\Order\Shipment $shipment */
                $this->shipmentSender->send($shipment);
            } catch (\Exception $e) {
                continue;
            }
        }
    }
}
