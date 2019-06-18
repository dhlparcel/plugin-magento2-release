<?php

namespace DHLParcel\Shipping\Model\Service\Logic;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Api\Connector;
use DHLParcel\Shipping\Model\Exception\LabelCreationException;
use DHLParcel\Shipping\Model\Piece;
use DHLParcel\Shipping\Model\PieceFactory;
use DHLParcel\Shipping\Model\UUID;
use DHLParcel\Shipping\Model\UUIDFactory;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment as ShipmentRequest;
use DHLParcel\Shipping\Model\Data\Api\Request\ShipmentFactory as ShipmentRequestFactory;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Addressee\Address;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\AddresseeFactory;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Option;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\OptionFactory;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Piece as PieceRequest;
use DHLParcel\Shipping\Model\Data\Api\Response\Shipment as ShipmentResponse;
use DHLParcel\Shipping\Model\Data\Api\Response\ShipmentFactory as ShipmentResponseFactory;
use DHLParcel\Shipping\Model\Data\Api\Response\Shipment\Piece as PieceResponse;
use DHLParcel\Shipping\Model\ResourceModel\Piece as PieceResource;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\Shipment\Track;

class Shipment
{
    protected $connector;
    protected $pieceFactory;
    protected $uuidFactory;
    protected $shipmentRequestFactory;
    protected $addresseeFactory;
    protected $optionFactory;
    protected $shipmentResponseFactory;
    protected $pieceResource;
    protected $orderRepository;
    protected $trackFactory;
    protected $helper;

    public function __construct(
        Connector $connector,
        PieceFactory $pieceFactory,
        UUIDFactory $uuidFactory,
        ShipmentRequestFactory $shipmentRequestFactory,
        AddresseeFactory $addresseeFactory,
        OptionFactory $optionFactory,
        ShipmentResponseFactory $shipmentResponseFactory,
        PieceResource $pieceResource,
        OrderRepositoryInterface $orderRepository,
        TrackFactory $trackFactory,
        Data $helper
    ) {
        $this->connector = $connector;
        $this->pieceFactory = $pieceFactory;
        $this->uuidFactory = $uuidFactory;
        $this->shipmentRequestFactory = $shipmentRequestFactory;
        $this->addresseeFactory = $addresseeFactory;
        $this->optionFactory = $optionFactory;
        $this->shipmentResponseFactory = $shipmentResponseFactory;
        $this->pieceResource = $pieceResource;
        $this->orderRepository = $orderRepository;
        $this->trackFactory = $trackFactory;
        $this->helper = $helper;
    }

    /**
     * @param $orderId
     * @param array $options
     * @param array $pieces
     * @param bool $isBusiness
     * @return ShipmentRequest
     */
    public function getRequestData($orderId, $options, $pieces, $isBusiness)
    {
        $randomUUID = (string)$this->uuidFactory->create();
        $order = $this->orderRepository->get($orderId);
        /** @var \Magento\Sales\Api\Data\OrderAddressInterface $receiverAddress */
        $receiverAddress = $order->getShippingAddress();
        $receiver = $this->getReceiverAddress($receiverAddress, $isBusiness);
        $shipper = $this->getShipperAddress();
        $accountId = $this->helper->getConfigData('api/account_id');
        $options = $this->validateOptions($options);
        $pieces = $this->validatePieces($pieces);

        /** @var ShipmentRequest $shipmentRequest */
        $shipmentRequest = $this->shipmentRequestFactory->create();
        $shipmentRequest->shipmentId = $randomUUID;
        $shipmentRequest->orderReference = (string)$orderId;
        $shipmentRequest->receiver = $receiver;
        $shipmentRequest->shipper = $shipper;
        $shipmentRequest->accountId = $accountId;
        $shipmentRequest->options = $options;
        $shipmentRequest->pieces = $pieces;
        $shipmentRequest->application = 'Magento2';

        return $shipmentRequest;
    }

    /**
     * @param ShipmentRequest $shipmentRequest
     * @return ShipmentRequest
     */
    public function getReturnRequestData(ShipmentRequest $shipmentRequest)
    {

        // Check for alternative return address with settings
        if ($this->helper->getConfigData('shipper/alternative_return_address')) {
            $receiver = $this->getShipperAddress('return');
        } elseif (!empty($shipmentRequest->onBehalfOf)) {
            $receiver = $shipmentRequest->onBehalfOf;
        } else {
            $receiver = $shipmentRequest->shipper;
        }

        // Check if there is an 'onBehalfOf' and unset it
        if (!empty($shipmentRequest->onBehalfOf)) {
            $shipmentRequest->onBehalfOf = null;
        }

        $shipper = $shipmentRequest->receiver;
        $randomUUID = (string)$this->uuidFactory->create();

        $shipmentRequest->shipmentId = $randomUUID;
        $shipmentRequest->receiver = $receiver;
        $shipmentRequest->shipper = $shipper;
        $shipmentRequest->returnLabel = true;

        // Return labels are DOOR only with no other options
        $option = $this->optionFactory->create(['automap' => [
            'key' => 'DOOR'
        ]]);
        $shipmentRequest->options = [$option];

        return $shipmentRequest;
    }

    /**
     * @param $shipmentRequest
     * @return ShipmentResponse|null
     */
    public function sendRequest(ShipmentRequest $shipmentRequest)
    {
        $response = $this->connector->post('shipments', $shipmentRequest->toArray(), true);

        if (!$response) {
            return null;
        }

        /** @var ShipmentResponse $shipmentResponse */
        $shipmentResponse = $this->shipmentResponseFactory->create(['automap' => $response]);
        // Enrich pieces with postalCode
        $shipmentResponse = $this->tagPostalCode($shipmentResponse, $shipmentRequest->receiver->address->postalCode);
        return $shipmentResponse;
    }

    /**
     * @param PieceResponse[] $pieceResponses
     * @param bool $isReturn
     * @return Track[]
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws LabelCreationException
     */
    public function createTracks($pieceResponses, $isReturn = false)
    {
        if (empty($pieceResponses)) {
            return [];
        }

        $tracks = [];
        foreach ($pieceResponses as $pieceResponse) {
            /** @var Piece $piece */
            $piece = $this->pieceFactory->create();
            $piece->addData([
                'label_id'     => $pieceResponse->labelId,
                'tracker_code' => $pieceResponse->trackerCode,
                'postal_code'  => $pieceResponse->postalCode,
                'parcel_type'  => $pieceResponse->parcelType,
                'piece_number' => $pieceResponse->pieceNumber,
                'label_type'   => $pieceResponse->labelType,
                'is_return'    => $isReturn,
            ]);

            $this->pieceResource->save($piece);

            /** @var Track $track */
            $track = $this->trackFactory->create([]);
            $track->addData([
                'carrier_code' => 'dhlparcel',
                'title'        => !$isReturn ? 'DHL Parcel' : 'DHL Parcel Return Label',
                'number'       => $pieceResponse->trackerCode,
            ]);

            $tracks[$pieceResponse->labelId] = $track;
        }

        return $tracks;
    }

    public function hideShipper($shipmentRequest)
    {
        $hideShipperAddress = $this->getShipperAddress('hide_shipper');
        $shipmentRequest->onBehalfOf = $hideShipperAddress;

        return $shipmentRequest;
    }

    public function addReference($shipmentRequest, $reference)
    {
        $shipmentRequest->options[] = ['key' => 'REFERENCE', 'input' => $reference];

        return $shipmentRequest;
    }

    protected function tagPostalCode(ShipmentResponse $shipmentResponse, $postalCode)
    {
        if (!empty($shipmentResponse) && !empty($shipmentResponse->pieces)) {
            $updatedPieces = [];
            foreach ($shipmentResponse->pieces as $piece) {
                $piece->postalCode = strtoupper(trim($postalCode));
                $updatedPieces[] = $piece;
            }
            $shipmentResponse->pieces = $updatedPieces;
        }
        return $shipmentResponse;
    }

    /**
     * @param string $group
     * @return Addressee
     */
    protected function getShipperAddress($group = '')
    {
        if ($group) {
            $group .= '/';
        }

        /** @var Addressee $addressee */
        $addressee = $this->addresseeFactory->create(['automap' => [
            'name'        => [
                'firstName'   => $this->helper->getConfigData('shipper/' . $group . 'first_name'),
                'lastName'    => $this->helper->getConfigData('shipper/' . $group . 'last_name'),
                'companyName' => $this->helper->getConfigData('shipper/' . $group . 'company_name'),
            ],
            'address'     => [
                'countryCode' => $this->helper->getConfigData('shipper/country_code'),
                'postalCode'  => strtoupper($this->helper->getConfigData('shipper/' . $group . 'postal_code')),
                'city'        => $this->helper->getConfigData('shipper/' . $group . 'city'),
                'street'      => $this->helper->getConfigData('shipper/' . $group . 'street'),
                'number'      => $this->helper->getConfigData('shipper/' . $group . 'house_number'),
                'isBusiness'  => true,
                'addition'    => $this->helper->getConfigData('shipper/' . $group . 'house_number_addition'),
            ],
            'email'       => $this->helper->getConfigData('shipper/' . $group . 'email'),
            'phoneNumber' => $this->helper->getConfigData('shipper/' . $group . 'phone'),
        ]]);

        return $addressee;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $address
     * @param bool $isBusiness
     * @return Addressee
     */
    protected function getReceiverAddress(\Magento\Sales\Api\Data\OrderAddressInterface $address, $isBusiness)
    {
        $email = $address->getOrder()->getCustomerEmail();
        $phoneNumber = $address->getTelephone();

        /** @var Addressee $addressee */
        $addressee = $this->addresseeFactory->create([
            'automap' => [
                'name'        => [
                    'firstName'   => $address->getFirstname(),
                    'lastName'    => $address->getLastname(),
                    'companyName' => $address->getCompany(),
                ],
                'address'     => [
                    'countryCode' => $address->getCountryId(),
                    'postalCode'  => strtoupper($address->getPostcode()),
                    'city'        => $address->getCity(),
                    'street'      => '',
                    'number'      => '',
                    'isBusiness'  => $isBusiness,
                    'addition'    => '',
                ],
                'email'       => $email,
                'phoneNumber' => $phoneNumber,
            ],
        ]);

        $addressee->address = $this->updateAddressStreet($addressee->address, $address->getStreet());
        return $addressee;
    }

    protected function updateAddressStreet(Address $address, array $street)
    {
        $fullStreet = implode(' ', $street);
        $regex = '/((?<pre_number>[\d-]*\d)[.-]?(?<pre_addition>\S+)?\s)?(?<street>[^\d\n]+)\s?((?<number>[\d-]*\d)[ .-]?(?<addition>\S+)?)?/i';
        $matchFound = preg_match($regex, $fullStreet, $matches);

        if ($matchFound) {
            $address->street = $matches['street'];

            if (isset($matches['pre_number']) && is_string($matches['pre_number']) && strlen($matches['pre_number']) > 0) {
                // House number before street name
                $address->number = $matches['pre_number'];

                if (isset($matches['pre_addition']) && is_string($matches['pre_addition']) && strlen($matches['pre_addition']) > 0) {
                    $address->addition = $matches['pre_addition'];
                }
            } elseif (isset($matches['number']) && is_string($matches['number']) && strlen($matches['number']) > 0) {
                // House number after street name
                $address->number = $matches['number'];

                if (isset($matches['addition']) && is_string($matches['addition']) && strlen($matches['addition']) > 0) {
                    $address->addition = $matches['addition'];
                }
            }
        }

        return $address;
    }

    /**
     * @param Option[] $options
     * @return Option[]
     */
    protected function validateOptions(array $options)
    {
        foreach ($options as $key => $option) {
            if (!$option instanceof Option) {
                unset($options[$key]);
            }
        }

        return array_values($options);
    }

    /**
     * @param PieceRequest[] $pieces
     * @return PieceRequest[]
     */
    protected function validatePieces(array $pieces)
    {
        foreach ($pieces as $key => $piece) {
            if (!$piece instanceof PieceRequest) {
                unset($pieces[$key]);
            }
        }
        return $pieces;
    }
}
