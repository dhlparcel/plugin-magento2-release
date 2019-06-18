<?php

namespace DHLParcel\Shipping\Model\Data\Api\Response;

use DHLParcel\Shipping\Model\Data\AbstractData;

class TimeWindow extends AbstractData
{
    public $postalCode;
    public $deliveryDate;
    public $type;
    public $startTime;
    public $endTime;
    public $status;
}
