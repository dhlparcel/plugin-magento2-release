<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Model\Exception\FaultyServiceOptionException;
use DHLParcel\Shipping\Model\Exception\LabelCreationException;
use DHLParcel\Shipping\Model\Exception\NoTrackException;
use DHLParcel\Shipping\Model\Exception\NotShippableException;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Sales\Model\ResourceModel\Order\Shipment as ShipmentResource;
use Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader;

class Order
{
    protected $shipmentLoader;
    protected $shipmentResource;
    protected $transactionFactory;
    protected $shipmentRepository;
    protected $registry;

    public function __construct(
        ShipmentLoader $shipmentLoader,
        ShipmentResource $shipmentResource,
        TransactionFactory $transactionFactory,
        Registry $registry,
        ShipmentRepository $shipmentRepository
    ) {
        $this->shipmentLoader = $shipmentLoader;
        $this->shipmentResource = $shipmentResource;
        $this->transactionFactory = $transactionFactory;
        $this->registry = $registry;
        $this->shipmentRepository = $shipmentRepository;
    }

    public function createShipment($orderId)
    {
        $this->shipmentLoader->setOrderId($orderId);
        $this->preloadShipment();
        $shipment = $this->shipmentLoader->load();
        if ($shipment === false) {
            throw new NotShippableException(__("A shipment cannot be created for the order"));
        }

        try {
            $shipment->setData('dhlparcel_shipping_is_created', true);
            $this->shipmentResource->saveAttribute($shipment, ['dhlparcel_shipping_is_created']);
            $shipment->register();
            $this->saveShipment($shipment);
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

        return $shipment;
    }

    public function undoShipment($shipmentId)
    {
        /** @var Shipment $shipment */
        $shipment = $this->shipmentRepository->get($shipmentId);
        if (!$shipment) {
            throw new NoSuchEntityException();
        }

        $canShip = $shipment->getOrder()
            ->canShip();

        // Order is shippable
        if ($canShip === true) {
            return true;
        }

        // Shipped items
        foreach ($shipment->getItemsCollection() as $shippedItem) {
            $orderItem = $shippedItem->getOrderItem();
            if ($orderItem) {
                $orderItem->setQtyShipped(0)
                    ->save();
            }
        }

        return $shipment->getOrder()
            ->canShip();
    }

    /**
     * Save shipment and order in one transaction
     *
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     *
     * @return $this
     * @throws \Exception
     */
    protected function saveShipment($shipment)
    {
        $shipment->getOrder()
            ->setIsInProcess(true);
        /** @var \Magento\Framework\DB\Transaction $transaction */
        $transaction = $this->transactionFactory->create();
        $transaction->addObject($shipment)
            ->addObject($shipment->getOrder())
            ->save();

        return $this;
    }

    /**
     * Magento's shipmentLoader sets a deprecated 'current_shipment', but doesn't seem to clear it causing errors.
     * We need to use their deprecated method to unregister.
     * Might need to update this in the future.
     */
    protected function preloadShipment()
    {
        $this->registry->unregister('current_shipment');
    }
}
