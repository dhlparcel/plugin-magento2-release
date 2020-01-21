<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Model\Exception\NotShippableException;
use DHLParcel\Shipping\Model\Exception\FaultyServiceOptionException;
use DHLParcel\Shipping\Model\Exception\LabelCreationException;
use DHLParcel\Shipping\Model\Exception\NoTrackException;

use Magento\Framework\Exception\LocalizedException;

class Order
{
    protected $orderRepository;
    protected $convertOrder;
    protected $shipmentNotifier;
    protected $shipmentRepository;

    /**
     * Order constructor.
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Convert\Order $convertOrder
     * @param \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderResource
     * @param \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Convert\Order $convertOrder,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier,
        \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->convertOrder = $convertOrder;
        $this->shipmentNotifier = $shipmentNotifier;
        $this->shipmentRepository = $shipmentRepository;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @throws NotShippableException
     * @throws FaultyServiceOptionException
     * @throws LabelCreationException
     * @throws NoTrackException
     * @throws LocalizedException
     */
    public function createShipment(\Magento\Sales\Model\Order $order)
    {
        if (!$order->canShip()) {
            throw new NotShippableException(__("A shipment cannot be created for the order"));
        }

        $shipment = $this->convertOrder->toShipment($order);

        foreach ($order->getAllItems() as $orderItem) {
            // Check virtual item and item Quantity
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }

            $qty = $orderItem->getQtyToShip();
            $shipmentItem = $this->convertOrder->itemToShipmentItem($orderItem);
            $shipmentItem->setQty($qty);
            $shipment->addItem($shipmentItem);
        }

        $shipment->register();
        $order->setIsInProcess(true);
        try {
            // Save created Order Shipment
            $this->shipmentRepository->save($shipment);
            $this->orderRepository->save($order);

            // Send Shipment Email
            $this->shipmentNotifier->notify($shipment);
        } catch (\Exception $e) {
            if ($e instanceof FaultyServiceOptionException) {
                throw new FaultyServiceOptionException(__($e->getMessage()), $e);
            } elseif ($e instanceof LabelCreationException) {
                throw new LabelCreationException(__($e->getMessage()), $e);
            } elseif ($e instanceof NoTrackException) {
                throw new NoTrackException(__($e->getMessage()), $e);
            } elseif ($e instanceof LocalizedException) {
                throw $e;
            } else {
                throw new LocalizedException(__($e->getMessage()), $e);
            }
        }
    }
}
