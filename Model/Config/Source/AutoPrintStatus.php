<?php

namespace DHLParcel\Shipping\Model\Config\Source;

class AutoPrintStatus implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \Magento\Sales\Model\Config\Source\Order\Status
     */
    protected $coreStatus;

    /**
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     */
    public function __construct(\Magento\Sales\Model\Config\Source\Order\Status $coreStatus)
    {
        $this->coreStatus = $coreStatus;
    }

    public function toOptionArray()
    {
        $coreStatuses = $this->coreStatus->toOptionArray();

        $statuses = array();
        foreach ($coreStatuses as $i => $status) {
            if ($status['value'] != 'pending' &&
                $status['value'] != 'complete' &&
                $status['value'] != 'canceled' &&
                $status['value'] != 'closed') {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }
}
