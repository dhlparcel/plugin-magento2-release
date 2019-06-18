<?php

namespace DHLParcel\Shipping\Model\Data\Api\Request;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Shipment extends AbstractData
{
    public $shipmentId;
    public $orderReference;
    /** @var  \DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee */
    public $receiver;
    /** @var  \DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee */
    public $shipper;
    /** @var  \DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee */
    public $onBehalfOf;
    public $accountId;
    /** @var  \DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Option[] */
    public $options;
    public $returnLabel;
    /** @var  \DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Piece[] */
    public $pieces;
    public $application;

    protected function getClassMap()
    {
        return [
            'receiver'   => 'DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee',
            'shipper'    => 'DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee',
            'onBehalfOf' => 'DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee',
        ];
    }

    protected function getClassArrayMap()
    {
        return [
            'options' => 'DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Option',
            'pieces'  => 'DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Piece',
        ];
    }
}
