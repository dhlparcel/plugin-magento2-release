<?php

namespace DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Address extends AbstractData
{
    public $countryCode;
    public $postalCode;
    public $city;
    public $street;
    public $number;
    public $isBusiness;
    public $addition;
}
