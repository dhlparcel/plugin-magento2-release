<?php

namespace DHLParcel\Shipping\Model\Data\Api\Response;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Shipment extends AbstractData
{
    public $shipmentId;
    /** @var \DHLParcel\Shipping\Model\Data\Api\Response\Shipment\Piece[] */
    public $pieces;

    protected function getClassArrayMap()
    {
        return [
            'pieces' => 'DHLParcel\Shipping\Model\Data\Api\Response\Shipment\Piece',
        ];
    }
}
