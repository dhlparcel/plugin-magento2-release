<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Model\Piece;
use DHLParcel\Shipping\Model\PieceFactory;
use DHLParcel\Shipping\Model\ResourceModel\Piece as PieceResource;
use Magento\Store\Model\StoreManagerInterface;

class Returns
{
    protected $pieceResource;
    protected $pieceFactory;
    /** @var StoreManagerInterface */
    protected $storeManager;

    public function __construct(
        StoreManagerInterface $storeManager,
        PieceResource $pieceResource,
        PieceFactory $pieceFactory
    ) {

        $this->storeManager = $storeManager;
        $this->pieceResource = $pieceResource;
        $this->pieceFactory = $pieceFactory;
    }

    public function cleanupReturnTracks($tracks)
    {

        foreach ($tracks as $key => $track) {
            if ($track->getCarrierCode() == 'dhlparcel' || $track->getCarrier() == 'dhlparcel') {
                // TODO move this to a service function getPiece($track)
                $trackNumber = $track->getTrackNumber();
                if (empty($trackNumber)) {
                    $trackNumber = $track->getTracking();
                }
                
                /** @var Piece $piece */
                $piece = $this->pieceFactory->create();
                $this->pieceResource->load($piece, $trackNumber, 'tracker_code');
                $isReturn = $piece->getIsReturn();
                if ($isReturn) {
                    // Remove if it's a return label
                    if (is_array($tracks)) {
                        unset($tracks[$key]);
                    } else {
                        $tracks->removeItemByKey($key);
                    }
                }
            }
        }

        return $tracks;
    }
}
