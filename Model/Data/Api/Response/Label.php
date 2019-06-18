<?php

namespace DHLParcel\Shipping\Model\Data\Api\Response;

use DHLParcel\Shipping\Model\Data\AbstractData;

class Label extends AbstractData
{
    public $labelId;
    public $labelType;
    public $trackerCode;
    public $pieceNumber;
    public $routingCode;
    public $userId;
    public $organizationId;
    public $orderReference;
    public $pdf;
    public $application;
}
