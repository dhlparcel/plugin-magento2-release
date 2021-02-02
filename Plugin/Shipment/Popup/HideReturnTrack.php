<?php

namespace DHLParcel\Shipping\Plugin\Shipment\Popup;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\ResourceModel\Piece as PieceResource;
use DHLParcel\Shipping\Model\Piece;
use DHLParcel\Shipping\Model\PieceFactory;
use DHLParcel\Shipping\Model\Service\Returns as ReturnService;
use Magento\Store\Model\StoreManagerInterface;

class HideReturnTrack
{
    /** @var Returns */
    protected $returnService;

    /** @var Data */
    protected $helper;

    public function __construct(
        ReturnService $returnService,
        Data $helper
    ) {
        $this->returnService = $returnService;
        $this->helper = $helper;
    }

    public function afterGetTrackingInfo(\Magento\Shipping\Block\Tracking\Popup $subject, $shipments)
    {
        if (boolval($this->helper->getConfigData('usability/return_tracks/show_for_customers'))) {
            return $shipments;
        }

        foreach ($shipments as $shipmentKey => $shipment) {
            $shipments[$shipmentKey] = $this->returnService->cleanupReturnTracks($shipment);
        }

        return $shipments;
    }
}
