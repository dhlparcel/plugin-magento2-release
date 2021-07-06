<?php

namespace DHLParcel\Shipping\Model\Data;

class TimeSelection extends AbstractData
{
    public $date;
    public $startTime;
    public $endTime;
    public $timestamp;

    public function getDisplayString()
    {
        $dateTimeStringStart = date_create_from_format('d-m-Y Hi', $this->date . ' ' . $this->startTime);
        $dateTimeStringEnd = date_create_from_format('d-m-Y Hi', $this->date . ' ' . $this->endTime);

        $betweenTimes = $dateTimeStringStart->format('H:i') . ' - ' . $dateTimeStringEnd->format('H:i');

        return implode(' ', [
                $dateTimeStringStart->format('d'),
                __($dateTimeStringStart->format('M')) . '.',
                $dateTimeStringStart->format('Y'),
                $betweenTimes
        ]);
    }
}
