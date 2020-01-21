<?php

namespace DHLParcel\Shipping\Plugin\Adminhtml\Shipment;

class PrintButton extends \DHLParcel\Shipping\Plugin\Adminhtml\AbstractShipmentsButton
{
    public function beforeSetLayout(\Magento\Shipping\Block\Adminhtml\View $subject)
    {
        $shipment = $subject->getShipment();
        if (!$shipment) {
            return;
        }

        $tracks = $shipment->getTracks();
        if (empty($tracks)) {
            // Don't show button if no DHL labels are found
            return;
        }

        $labelsFound = false;
        foreach ($tracks as $track) {
            if ($track->getCarrierCode() == 'dhlparcel') {
                $labelsFound = true;
            }
        }
        if ($labelsFound === false) {
            return;
        }

        $this->addButtons($subject, $shipment->getId());
    }
}
