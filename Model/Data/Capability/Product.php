<?php

namespace DHLParcel\Shipping\Model\Data\Capability;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Product extends AbstractData
{
    public $key;
    public $minWeightKg;
    public $maxWeightKg;
    /** @var \DHLParcel\Shipping\Model\Data\Api\Response\Capability\ParcelType\Dimension */
    public $dimensions;
    public $productKey;

    protected function getClassMap()
    {
        return [
            'dimensions' => 'DHLParcel\Shipping\Model\Data\Api\Response\Capability\ParcelType\Dimension',
        ];
    }
}
