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
    protected $orderResource;
    protected $shipmentResource;

    /**
     * Order constructor.
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Convert\Order $convertOrder
     * @param \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier
     * @param \Magento\Sales\Model\ResourceModel\Order $orderResource
     * @param \Magento\Sales\Model\ResourceModel\Order\Shipment $shipmentResource
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Convert\Order $convertOrder,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier,
        \Magento\Sales\Model\ResourceModel\Order $orderResource,
        \Magento\Sales\Model\ResourceModel\Order\Shipment $shipmentResource
    ) {
        $this->orderRepository = $orderRepository;
        $this->convertOrder = $convertOrder;
        $this->shipmentNotifier = $shipmentNotifier;
        $this->orderResource = $orderResource;
        $this->shipmentResource = $shipmentResource;
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
            throw new NotShippableException(__("A shipment cannot be created for order"));
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
            $this->shipmentResource->save($shipment);
            $this->orderResource->save($order);

            // Send Shipment Email
            $this->shipmentNotifier->notify($shipment);
        } catch (\Exception $e) {
            if ($e instanceof FaultyServiceOptionException) {
                throw new FaultyServiceOptionException(__($e->getMessage()), $e);
            } elseif ($e instanceof LabelCreationException) {
                throw new LabelCreationException(__($e->getMessage()), $e);
            } elseif ($e instanceof NoTrackException) {
                throw new NoTrackException(__($e->getMessage()), $e);
            } else {
                throw new LocalizedException(__($e->getMessage()), $e);
            }
        }
    }
}
