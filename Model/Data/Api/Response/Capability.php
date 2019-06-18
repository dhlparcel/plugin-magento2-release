<?php

namespace DHLParcel\Shipping\Model\Data\Api\Response;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Capability extends AbstractData
{
    public $rank;
    public $fromCountryCode;
    public $toCountryCode;
    /** @var \DHLParcel\Shipping\Model\Data\Api\Response\Capability\Product */
    public $product;
    /** @var \DHLParcel\Shipping\Model\Data\Api\Response\Capability\ParcelType */
    public $parcelType;
    /** @var \DHLParcel\Shipping\Model\Data\Api\Response\Capability\Option[] */
    public $options;

    protected function getClassMap()
    {
        return [
            'product'    => 'DHLParcel\Shipping\Model\Data\Api\Response\Capability\Product',
            'parcelType' => 'DHLParcel\Shipping\Model\Data\Api\Response\Capability\ParcelType',
        ];
    }

    protected function getClassArrayMap()
    {
        return [
            'options' => 'DHLParcel\Shipping\Model\Data\Api\Response\Capability\Option',
        ];
    }
}
