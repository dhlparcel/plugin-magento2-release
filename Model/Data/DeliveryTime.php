<?php

namespace DHLParcel\Shipping\Model\Data;

class DeliveryTime extends AbstractData
{
    /** @var \DHLParcel\Shipping\Model\Data\Api\Response\TimeWindow */
    public $source;

    public $date;
    public $weekDay;
    public $day;
    public $month;
    public $year;

    public $startTime;
    public $endTime;

    public $displayLabel;
    public $identifier;

    protected function getClassMap()
    {
        return [
            'source' => 'DHLParcel\Shipping\Model\Data\Api\Response\TimeWindow',
        ];
    }
}
