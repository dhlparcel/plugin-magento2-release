<?php

namespace DHLParcel\Shipping\Plugin\Email\Shipment;

use DHLParcel\Shipping\Model\ResourceModel\Piece as PieceResource;
use DHLParcel\Shipping\Model\Piece;
use DHLParcel\Shipping\Model\PieceFactory;

class Track
{

    protected $pieceResource;
    protected $pieceFactory;

    public function __construct(
        PieceResource $pieceResource,
        PieceFactory $pieceFactory
    ) {
        $this->pieceResource = $pieceResource;
        $this->pieceFactory = $pieceFactory;
    }

    public function afterGetAllTracks(\Magento\Sales\Model\Order\Shipment $subject, $tracks)
    {
        // Expected the template to be at around trace #4, but depending on the amount of interceptors this might differ
        // Thus the limit is set higher to offset this
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 14);

        $isTrackTemplate = false;
        foreach ($traces as $trace) {
            if (!empty($trace['args']) && is_array($trace['args'])) {
                $search = 'email/shipment/track.phtml';
                $templateFile = $trace['args'][0];
                if (substr($templateFile, -strlen($search)) === $search) {
                    $isTrackTemplate = true;
                }
            }
        }

        if (!$isTrackTemplate) {
            return $tracks;
        }

        /** @var \Magento\Sales\Api\Data\ShipmentTrackInterface $track */
        foreach ($tracks as $key => $track) {
            if ($track->getCarrierCode() == 'dhlparcel') {
                // TODO move this to a service function getPiece($track)
                $trackNumber = $track->getTrackNumber();
                /** @var Piece $piece */
                $piece = $this->pieceFactory->create();
                $this->pieceResource->load($piece, $trackNumber, 'tracker_code');
                $isReturn = $piece->getIsReturn();
                if ($isReturn) {
                    // Remove if it's a return label
                    unset($tracks[$key]);
                }
            }
        }

        return $tracks;
    }
}
