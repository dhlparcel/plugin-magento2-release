<?php

namespace DHLParcel\Shipping\Observer;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Service\Order as OrderService;
use DHLParcel\Shipping\Model\Service\Shipment as ShipmentService;
use DHLParcel\Shipping\Model\Service\Label as LabelService;
use DHLParcel\Shipping\Model\Service\Printing as PrintingService;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Sales\Model\Order;

class OrderSaveAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * @var ShipmentService
     */
    protected $shipmentService;

    /**
     * @var LabelService
     */
    protected $labelService;

    /**
     * @var PrintingService
     */
    protected $printingService;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * OrderSaveAfter constructor.
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param EventManager $eventManager
     * @param Data $helper
     * @param OrderService $orderService
     * @param ShipmentService $shipmentService
     * @param LabelService $labelService
     * @param PrintingService $printingService
     */
    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        EventManager $eventManager,
        Data $helper,
        OrderService $orderService,
        ShipmentService $shipmentService,
        LabelService $labelService,
        PrintingService $printingService
    ) {
        $this->productMetadata = $productMetadata;
        $this->helper = $helper;
        $this->orderService = $orderService;
        $this->shipmentService = $shipmentService;
        $this->labelService = $labelService;
        $this->printingService = $printingService;
        $this->eventManager = $eventManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // Don't do anything when autoprint is disabled.
        if (boolval($this->helper->getConfigData('usability/auto_print/enabled')) === false
            || strval($this->helper->getConfigData('usability/auto_print/on_order_status')) === '') {
            return;
        }

        /**
         * @var $order Order
         */
        $order = $observer->getEvent()->getOrder();

        if ($order->getStatus() == $this->helper->getConfigData('usability/auto_print/on_order_status')) {
            // Reload order object to get full-state order object
            $order = $this->orderService->getOrderById($order->getId());

            if ($order->canShip() && !$order->hasShipments()) {
                // Let's create shipment
                $shipmentId = $this->orderService->createShipment($order)->getId();

                // Reload Shipment By Id
                $shipment = $this->shipmentService->getShipmentById($shipmentId);

                // Call `dhlparcel_create_shipment` event to create a DHL label
                $this->eventManager->dispatch('dhlparcel_create_shipment', ['shipment' => $shipment]);

                // Order Created, auto print?
                if (boolval($this->helper->getConfigData('usability/auto_print/auto_print'))) {
                    $labelIds = $this->labelService->getShipmentLabelIds($shipment);
                    $this->printingService->sendPrintJob($shipment->getStoreId(), $labelIds);
                }
            }
        }
    }
}
