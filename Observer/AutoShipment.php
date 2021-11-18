<?php

namespace DHLParcel\Shipping\Observer;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Config\Source\YesNoDHL;
use DHLParcel\Shipping\Model\Service\Label as LabelService;
use DHLParcel\Shipping\Model\Service\Order as OrderService;
use DHLParcel\Shipping\Model\Service\Preset as PresetService;
use DHLParcel\Shipping\Model\Service\Printing as PrintingService;
use DHLParcel\Shipping\Model\Service\Shipment as ShipmentService;
use DHLParcel\Shipping\Model\Registry\CurrentAutoShipment;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

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
    protected $orderRepository;
    protected $currentAutoShipment;

    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        OrderRepositoryInterface $orderRepository,
        EventManager $eventManager,
        Data $helper,
        OrderService $orderService,
        ShipmentService $shipmentService,
        LabelService $labelService,
        PrintingService $printingService,
        PresetService $presetService,
        CurrentAutoShipment $currentAutoShipment
    ) {
        $this->productMetadata = $productMetadata;
        $this->orderRepository = $orderRepository;
        $this->helper = $helper;
        $this->orderService = $orderService;
        $this->shipmentService = $shipmentService;
        $this->labelService = $labelService;
        $this->printingService = $printingService;
        $this->presetService = $presetService;
        $this->eventManager = $eventManager;
        $this->currentAutoShipment = $currentAutoShipment;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // Don't do anything when auto shipment is disabled.
        if (!boolval($this->helper->getConfigData('usability/automation/shipment'))
            || strval($this->helper->getConfigData('usability/automation/on_order_status')) === '') {
            return;
        }

        $observerOrder = $observer->getEvent()->getOrder();
        if (!$observerOrder || !$observerOrder->getId()) {
            return;
        }

        if ($observerOrder->getStatus() !== $this->helper->getConfigData('usability/automation/on_order_status')) {
            return;
        }
        if (!$observerOrder->canShip() || $observerOrder->hasShipments() || $observerOrder->getShipmentsCollection()->count() > 0) {
            return;
        }

        /**
         * Force new order from database, looks like object is not always reliable
         *
         * @var $order Order
         */
        $order = $this->orderRepository->get($observer->getEvent()->getOrder()->getId());
        if (!$order->canShip() || $order->hasShipments() || $order->getShipmentsCollection()->count() > 0) {
            return;
        }

        if (intval($this->helper->getConfigData('usability/automation/shipment')) === YesNoDHL::OPTION_DHL && !$this->presetService->exists($order)) {
            return;
        }

        // Check if current autoShipment is already initiated
        if ($this->currentAutoShipment === $order->getId()) {
            // Skip auto shipment, it's already underway
            return;
        } else {
            $this->currentAutoShipment->setOrderId($order->getId());
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
