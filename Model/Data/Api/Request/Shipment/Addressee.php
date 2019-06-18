<?php

namespace DHLParcel\Shipping\Model\Data\Api\Request\Shipment;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Addressee extends AbstractData
{
    /** @var \DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee\Name */
    public $name;
    /** @var \DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee\Address */
    public $address;
    public $email;
    public $phoneNumber;

    protected function getClassMap()
    {
        return [
            'name'    => 'DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee\Name',
            'address' => 'DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee\Address',
        ];
    }
}
