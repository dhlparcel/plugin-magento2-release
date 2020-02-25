<?php

namespace DHLParcel\Shipping\Model\Data\Api\Response;

use DHLParcel\Shipping\Model\Data\AbstractData;

class ServicePoint extends AbstractData
{
    public $id;
    public $name;
    public $keyword;
    /** @var \DHLParcel\Shipping\Model\Data\Api\Response\ServicePoint\Address */
    public $address;
    public $geoLocation;
    public $distance;
    public $openingTimes;
    public $shopType;
    public $country;

    protected function getClassMap()
    {
        return [
            'address' => 'DHLParcel\Shipping\Model\Data\Api\Response\ServicePoint\Address',
        ];
    }
}
