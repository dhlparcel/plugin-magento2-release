<?php

namespace DHLParcel\Shipping\Model\Data\Api\Response\Capability;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Option extends AbstractData
{
    public $key;
    public $description;
    public $rank;
    public $code;
    public $inputType;
    public $inputMax;
    public $optionType;
    /** @var \DHLParcel\Shipping\Model\Data\Api\Response\Capability\Option[] */
    public $exclusions;

    protected function getClassArrayMap()
    {
        return [
            'exclusions' => 'DHLParcel\Shipping\Model\Data\Api\Response\Capability\Option',
        ];
    }
}
