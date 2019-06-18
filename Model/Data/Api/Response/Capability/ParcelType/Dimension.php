<?php

namespace DHLParcel\Shipping\Model\Data\Api\Response\Capability\ParcelType;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Dimension extends AbstractData
{
    public $maxLengthCm;
    public $maxWidthCm;
    public $maxHeightCm;
}
