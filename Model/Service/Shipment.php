<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Carrier;
use DHLParcel\Shipping\Model\Config\Source\YesNoTest;
use DHLParcel\Shipping\Model\Exception\LabelCreationException;
use DHLParcel\Shipping\Model\Service\Logic\Shipment as ShipmentLogic;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Option;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Piece;

class Shipment
{

    protected $connector;
    protected $optionFactory;
    protected $orderRepository;
    protected $scopeConfig;
    /** @var ShipmentLogic \DHLParcel\Shipping\Model\Service\Logic\Shipment */
    protected $shipmentLogic;
    protected $shipmentRepository;
    /** @var Data */
    protected $helper;

    public function __construct(
        \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository,
        ShipmentLogic                                  $shipmentLogic,
        Data                                           $helper
    ) {
        $this->shipmentLogic = $shipmentLogic;
        $this->shipmentRepository = $shipmentRepository;
        $this->helper = $helper;
    }

    /**
     * @param \Magento\Sales\Model\Order $order $order
     * @param array $options
     * @param array $pieces
     * @param bool $isBusiness
     * @return array
     * @throws \DHLParcel\Shipping\Model\Exception\LabelCreationException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function create($order, $options = [], $pieces = [], $isBusiness = false)
    {
        $orderId = $order->getId();
        $storeId = $order->getStoreId();
        $returnEnabled = $this->checkOption($options, 'ADD_RETURN_LABEL');
        if ($returnEnabled) {
            $options = $this->removeOption($options, 'ADD_RETURN_LABEL');
        }

        $shipmentRequest = $this->shipmentLogic->getRequestData($order, $options, $pieces, $isBusiness);

        $hideShipper = $this->checkOption($options, 'SSN');
        if ($hideShipper) {
            $shipmentRequest = $this->shipmentLogic->hideShipper($storeId, $shipmentRequest);
        }

        $pieceCount = $this->totalPieceQuantity($pieces);
        if ($pieceCount > 1 && $isBusiness === true && !$this->checkOption($options, 'REFERENCE')) {
            // Force Reference when multipiece & toBusiness
            $shipmentRequest = $this->shipmentLogic->addReference($shipmentRequest, $orderId);
        }

        $this->validateShipmentRequest($shipmentRequest, $hideShipper);
        if ($this->helper->getConfigData('active') != YesNoTest::OPTION_TEST) {
            $shipmentResponse = $this->shipmentLogic->sendRequest($shipmentRequest);
        } else {
            $shipmentResponse = $this->shipmentLogic->fakeRequest($shipmentRequest);
        }

        if (!$shipmentResponse) {
            throw new LabelCreationException(__('Failed to create label'));
        }

        $tracks = $this->shipmentLogic->createTracks($shipmentResponse->pieces);
        if (empty($tracks)) {
            throw new LabelCreationException(__('Failed to create label, issue occurred while creating track and trace link'));
        }

        if ($returnEnabled) {
            $returnShipmentRequest = $this->shipmentLogic->getReturnRequestData($storeId, $shipmentRequest);
            if ($this->helper->getConfigData('active') != YesNoTest::OPTION_TEST) {
                $returnShipmentResponse = $this->shipmentLogic->sendRequest($returnShipmentRequest);
            } else {
                $returnShipmentResponse = $this->shipmentLogic->fakeRequest($returnShipmentRequest);
            }
            $returnTracks = $this->shipmentLogic->createTracks($returnShipmentResponse->pieces, true);
            if (empty($returnTracks)) {
                //TODO code to handle reverting the
                throw new LabelCreationException(__('Failed to create return label'));
            }
            $tracks = array_merge($tracks, $returnTracks);
        }

        return $tracks;
    }

    /**
     * @param \DHLParcel\Shipping\Model\Data\Api\Request\Shipment $shipmentRequest
     * @param bool $hideShipper
     * @throws LabelCreationException
     */
    protected function validateShipmentRequest($shipmentRequest, $hideShipper = false)
    {
        if (empty($shipmentRequest->shipper->address->street)) {
            throw new LabelCreationException(__('Failed to create label, missing shipper street'));
        }

        $disableHousenumberValidationCountries = explode(',', strval($this->helper->getConfigData('usability/disable_housenumber_validation/countries')));
        $validateShipperHousenumber = (bool) !in_array($shipmentRequest->shipper->address->countryCode, $disableHousenumberValidationCountries);


        if ($validateShipperHousenumber && empty($shipmentRequest->shipper->address->number)) {
            throw new LabelCreationException(__('Failed to create label, missing shipper street number'));
        }

        if (empty($shipmentRequest->receiver->address->street)) {
            throw new LabelCreationException(__('Failed to create label, missing receiver street'));
        }

        $shouldSplitAddress = $this->shipmentLogic->shouldSplitAddress([$shipmentRequest->receiver->address->street, $shipmentRequest->receiver->address->number, $shipmentRequest->receiver->address->addition]);
        $validateReceiverHousenumber = (bool) !in_array($shipmentRequest->receiver->address->countryCode, $disableHousenumberValidationCountries);
        if ($validateReceiverHousenumber && empty($shipmentRequest->receiver->address->number) && $shouldSplitAddress) {
            throw new LabelCreationException(__('Failed to create label, missing receiver street number'));
        }

        if ($hideShipper) {
            if ((empty($shipmentRequest->onBehalfOf->name->firstName) || empty($shipmentRequest->onBehalfOf->name->lastName))
                && empty($shipmentRequest->onBehalfOf->name->companyName)) {
                throw new LabelCreationException(__('Failed to create label, missing hide shipper company name'));
            }

            if (empty($shipmentRequest->onBehalfOf->address->number)) {
                throw new LabelCreationException(__('Failed to create label, missing hide shipper street number'));
            }

            if (empty($shipmentRequest->onBehalfOf->address->street)) {
                throw new LabelCreationException(__('Failed to create label, missing hide shipper street'));
            }

            if (empty($shipmentRequest->onBehalfOf->address->city)) {
                throw new LabelCreationException(__('Failed to create label, missing hide shipper city'));
            }
        }
    }

    protected function checkOption($options, $key)
    {
        if (!is_array($options)) {
            return false;
        }

        foreach ($options as $option) {
            /** @var Option $option */
            if (strtoupper($option->key) == strtoupper($key)) {
                return true;
            }
        }

        return false;
    }

    protected function totalPieceQuantity($pieces)
    {
        $pieceQuantity = 0;
        foreach ($pieces as $piece) {
            /** @var Piece $piece */
            $pieceQuantity += $piece->quantity;
        }

        return $pieceQuantity;
    }

    protected function removeOption($options, $key)
    {
        if (!is_array($options)) {
            return $options;
        }

        foreach ($options as $optionKey => $option) {
            /** @var Option $option */
            if ($option->key == $key) {
                unset($options[$optionKey]);
            }
        }

        return $options;
    }

    public function getShipmentById($shipmentId)
    {
        return $this->shipmentRepository->get($shipmentId);
    }
}
