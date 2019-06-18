<?php

namespace DHLParcel\Shipping\Model\Data\Api\Response;

use DHLParcel\Shipping\Model\Data\AbstractData;

class ServicePoint extends AbstractData
{
    public $id;
    public $name;
    public $keyword;
    public $address;
    public $geoLocation;
    public $distance;
    public $openingTimes;
    public $country;
}
