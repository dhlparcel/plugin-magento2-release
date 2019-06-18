<?php

namespace DHLParcel\Shipping\Model\Data\Api\Request;

use DHLParcel\Shipping\Model\Data\AbstractData;

class CapabilityCheck extends AbstractData
{
    public $fromCountry;
    public $toCountry;
    public $toBusiness;
    public $returnProduct;
    public $parcelType;
    public $option;
    public $toPostalCode;
    public $accountNumber;
    public $organisationId;
}
