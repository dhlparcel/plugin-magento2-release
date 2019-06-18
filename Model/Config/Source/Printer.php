<?php

namespace DHLParcel\Shipping\Model\Config\Source;

use DHLParcel\Shipping\Model\Service\Printing as PrintingService;

class Printer implements \Magento\Framework\Option\ArrayInterface
{
    protected $printingService;

    /**
     * Printer constructor.
     * @param $printingService
     */
    public function __construct(PrintingService $printingService)
    {
        $this->printingService = $printingService;
    }

    public function toOptionArray()
    {
        $printers = [];
        foreach ($this->printingService->getPrinters() as $printer) {
            $printers[$printer->id] = $printer->name . ' - ' . $printer->timeRegistered;
        }
        if (count($printers) === 0) {
            $printers[] = __('No printers found');
        }
        return $printers;
    }
}
