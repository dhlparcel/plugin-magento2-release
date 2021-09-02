<?php

namespace DHLParcel\Shipping\Observer;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Config\Source\YesNoDHL;
use DHLParcel\Shipping\Model\Service\Label as LabelService;
use DHLParcel\Shipping\Model\Service\Order as OrderService;
use DHLParcel\Shipping\Model\Service\Preset as PresetService;
use DHLParcel\Shipping\Model\Service\Printing as PrintingService;
use DHLParcel\Shipping\Model\Service\Shipment as ShipmentService;
use Magento\Framework\Event\ManagerInterface as EventManager;

class AutoShipment implements \Magento\Framework\Event\ObserverInterface
{
    protected $helper;
    protected $orderService;
    protected $shipmentService;
    protected $labelService;
    protected $printingService;
    protected $presetService;
    protected $eventManager;
    protected $productMetadata;

    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        EventManager $eventManager,
        Data $helper,
        OrderService $orderService,
        ShipmentService $shipmentService,
        LabelService $labelService,
        PrintingService $printingService,
        PresetService $presetService
    ) {
        $this->productMetadata = $productMetadata;
        $this->helper = $helper;
        $this->orderService = $orderService;
        $this->shipmentService = $shipmentService;
        $this->labelService = $labelService;
        $this->printingService = $printingService;
        $this->presetService = $presetService;
        $this->eventManager = $eventManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // Don't do anything when auto shipment is disabled.
        if (!boolval($this->helper->getConfigData('usability/automation/shipment'))
            || strval($this->helper->getConfigData('usability/automation/on_order_status')) === '') {
            return;
        }

        $order = $observer->getEvent()->getOrder();

        if ($order->getStatus() !== $this->helper->getConfigData('usability/automation/on_order_status')) {
            return;
        }

        if (!$order->canShip() || $order->hasShipments() || !empty($order->getShipmentsCollection()->getData())) {
            return;
        }

        if (intval($this->helper->getConfigData('usability/automation/shipment')) === YesNoDHL::OPTION_DHL && !$this->presetService->exists($order)) {
            return;
        }

        $shipment = $this->orderService->createShipment($order->getId());

        if (boolval($this->helper->getConfigData('usability/automation/print'))) {
            // Reload shipment (not the shipment data in memory but from database with the newly created tracks) and send to printer
            $shipment = $this->shipmentService->getShipmentById($shipment->getId());
            $labelIds = $this->labelService->getShipmentLabelIds($shipment);
            $this->printingService->sendPrintJob($shipment->getStoreId(), $labelIds);
        }
    }
}
