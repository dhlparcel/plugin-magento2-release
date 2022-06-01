<?php

namespace DHLParcel\Shipping\Plugin\Adminhtml\Order;

class PrintButton extends \DHLParcel\Shipping\Plugin\Adminhtml\AbstractShipmentsButton
{
    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\View $subject)
    {
        $order = $subject->getOrder();

        if (!$order->hasShipments()) {
            return;
        }

        $shipments = $order->getShipmentsCollection();

        $labelsFound = false;
        foreach ($shipments as $shipment) {
            $tracks = $shipment->getAllTracks();
            if (empty($tracks)) {
                // Don't show button if no DHL labels are found
                continue;
            }

            $labelsFound = false;
            foreach ($tracks as $track) {
                if ($track->getCarrierCode() == 'dhlparcel') {
                    $labelsFound = true;
                }
            }
        }

        if ($labelsFound === false) {
            return;
        }

        $this->addButtons($subject, $shipment);
    }
}
