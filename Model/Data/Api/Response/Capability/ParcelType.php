<?php

namespace DHLParcel\Shipping\Model\Data\Api\Response\Capability;

use DHLParcel\Shipping\Model\Data\AbstractData;

class ParcelType extends AbstractData
{
    public $key;
    public $minWeightKg;
    public $maxWeightKg;
    /** @var \DHLParcel\Shipping\Model\Data\Api\Response\Capability\ParcelType\Dimension */
    public $dimensions;

    protected function getClassMap()
    {
        return [
            'dimensions' => 'DHLParcel\Shipping\Model\Data\Api\Response\Capability\ParcelType\Dimension',
        ];
    }
}
