<?php

namespace DHLParcel\Shipping\Model\Data\Api\Request\Shipment;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Piece extends AbstractData
{
    public $parcelType;
    public $quantity;
}
