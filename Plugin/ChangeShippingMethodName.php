<?php
namespace DHLParcel\Shipping\Plugin;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Data\TimeSelection;
use DHLParcel\Shipping\Model\Data\TimeSelectionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class ChangeShippingMethodName
{
    /**
     * @var TimeSelectionFactory
     */
    protected $timeSelectionFactory;

    /**
     * @var Data
     */
    protected $helper;

    public function __construct(
        TimeSelectionFactory $timeSelectionFactory,
        Data $helper
    ) {
        $this->timeSelectionFactory = $timeSelectionFactory;
        $this->helper = $helper;
    }

    public function beforeSetShippingDescription(Order $order, $description)
    {
        if (!boolval($this->helper->getConfigData('delivery_times/save_to_shippingdescription'))) {
            return [ $description ];
        }

        if (empty($order->getData('dhlparcel_shipping_deliverytimes_selection'))) {
            return [ $description ];
        }

        $shippingRequestedDate = $order->getData('dhlparcel_shipping_deliverytimes_selection');
        if (!$shippingRequestedDateData = json_decode($shippingRequestedDate)) {
            return [ $description ];
        }

        /**
         * @var TimeSelection $timeSelection
         */
        $timeSelection = $this->timeSelectionFactory->create(['automap' => (array) $shippingRequestedDateData]);

        if ($timeSelection->getDisplayString() === '') {
            return [ $description ];
        }

        return [ $description . ' (' . $timeSelection->getDisplayString() .')' ];
    }
}
