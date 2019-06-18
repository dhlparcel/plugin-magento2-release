<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Model\Exception\LabelCreationException;
use DHLParcel\Shipping\Model\Service\Logic\Shipment as ShipmentLogic;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Option;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Piece;

class Shipment
{

    protected $connector;
    protected $uuidFactory;
    protected $shipmentRequestFactory;
    protected $shipmentResponseFactory;
    protected $addresseeFactory;
    protected $optionFactory;
    protected $pieceFactory;
    protected $orderRepository;
    protected $scopeConfig;
    /** @var ShipmentLogic \DHLParcel\Shipping\Model\Service\Logic\Shipment */
    protected $shipmentLogic;

    public function __construct(
        ShipmentLogic $shipmentLogic
    ) {
        $this->shipmentLogic = $shipmentLogic;
    }

    /**
     * @param $orderId
     * @param array $options
     * @param array $pieces
     * @param bool $isBusiness
     * @return array
     * @throws \DHLParcel\Shipping\Model\Exception\LabelCreationException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function create($orderId, $options = [], $pieces = [], $isBusiness = false)
    {
        $returnEnabled = $this->checkOption($options, 'ADD_RETURN_LABEL');
        if ($returnEnabled) {
            $options = $this->removeOption($options, 'ADD_RETURN_LABEL');
        }

        $shipmentRequest = $this->shipmentLogic->getRequestData($orderId, $options, $pieces, $isBusiness);

        $hideShipper = $this->checkOption($options, 'SSN');
        if ($hideShipper) {
            $shipmentRequest = $this->shipmentLogic->hideShipper($shipmentRequest);
        }

        $pieceCount = $this->totalPieceQuantity($pieces);
        if ($pieceCount > 1 && $isBusiness === true && !$this->checkOption($options, 'REFERENCE')) {
            // Force Reference when multipiece & toBusiness
            $shipmentRequest = $this->shipmentLogic->addReference($shipmentRequest, $orderId);
        }

        $this->validateShipmentRequest($shipmentRequest, $hideShipper);
        $shipmentResponse = $this->shipmentLogic->sendRequest($shipmentRequest);
        if (!$shipmentResponse) {
            throw new LabelCreationException(__('Failed to create label'));
        }

        $tracks = $this->shipmentLogic->createTracks($shipmentResponse->pieces);
        if (empty($tracks)) {
            throw new LabelCreationException(__('Failed to create label'));
        }

        if ($returnEnabled) {
            $returnShipmentRequest = $this->shipmentLogic->getReturnRequestData($shipmentRequest);
            $this->validateShipmentRequest($shipmentRequest);
            $returnShipmentResponse = $this->shipmentLogic->sendRequest($returnShipmentRequest);
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
     */
    protected function validateShipmentRequest($shipmentRequest, $hideShipper = false)
    {
        if (empty($shipmentRequest->shipper->address->street)) {
            throw new LabelCreationException(__('Failed to create label, missing shipper street'));
        }

        if (empty($shipmentRequest->shipper->address->number)) {
            throw new LabelCreationException(__('Failed to create label, missing shipper street number'));
        }

        if (empty($shipmentRequest->receiver->address->street)) {
            throw new LabelCreationException(__('Failed to create label, missing receiver street'));
        }

        if (empty($shipmentRequest->receiver->address->number)) {
            throw new LabelCreationException(__('Failed to create label, missing receiver street number'));
        }

        if ($hideShipper) {
            if ((empty($shipmentRequest->onBehalfOf->name->firstName) || empty($shipmentRequest->onBehalfOf->name->lastName))
                && empty($shipmentRequest->onBehalfOf->name->companyName) ) {
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
}
