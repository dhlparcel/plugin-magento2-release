<?php

namespace DHLParcel\Shipping\Model\Data\Api\Response\Shipment;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Piece extends AbstractData
{

    public $labelId;
    public $trackerCode;
    public $parcelType;
    public $pieceNumber;
    public $labelType;

    // Custom internal field
    public $postalCode;
}
