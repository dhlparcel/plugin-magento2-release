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
     * @param \Magento\Sales\Model\Order $order $order
     * @param array $options
     * @param array $pieces
     * @param bool $isBusiness
     * @return ShipmentRequest
     */
    public function getRequestData($order, $options, $pieces, $isBusiness)
    {
        $storeId = $order->getStoreId();
        $randomUUID = (string)$this->uuidFactory->create();
        /** @var \Magento\Sales\Api\Data\OrderAddressInterface $receiverAddress */
        $receiverAddress = $order->getShippingAddress();
        $receiver = $this->getReceiverAddress($receiverAddress, $isBusiness);
        $shipper = $this->getShipperAddress($storeId);
        $accountId = $this->helper->getConfigData('api/account_id', $storeId);
        $options = $this->validateOptions($options);
        $pieces = $this->validatePieces($pieces);

        /** @var ShipmentRequest $shipmentRequest */
        $shipmentRequest = $this->shipmentRequestFactory->create();
        $shipmentRequest->shipmentId = $randomUUID;
        $shipmentRequest->orderReference = (string)$order->getId();
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
     * @param $storeId
     * @return ShipmentRequest
     */
    public function getReturnRequestData($storeId, ShipmentRequest $shipmentRequest)
    {

        // Check for alternative return address with settings
        if ($this->helper->getConfigData('shipper/alternative_return_address', $storeId)) {
            $receiver = $this->getShipperAddress($storeId, 'return');
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
     * @param ShipmentRequest $shipmentRequest
     * @return ShipmentResponse|null
     * @throws LabelCreationException
     */
    public function sendRequest(ShipmentRequest $shipmentRequest)
    {
        $response = $this->connector->post('shipments', $shipmentRequest->toArray());

        if (!$response) {
            if ($this->helper->getConfigData('debug/enabled')) {
                throw new LabelCreationException(__('Failed to create label: %1', $this->connector->errorMessage));
            } else {
                throw new LabelCreationException(__('Failed to create label'));
            }
        }

        /** @var ShipmentResponse $shipmentResponse */
        $shipmentResponse = $this->shipmentResponseFactory->create(['automap' => $response]);
        // Enrich pieces with postalCode
        $shipmentResponse = $this->tagPostalCode($shipmentResponse, $shipmentRequest->receiver->address->postalCode);
        $shipmentResponse = $this->tagCountryCode($shipmentResponse, $shipmentRequest->receiver->address->countryCode);
        $shipmentResponse = $this->tagShipmentRequest($shipmentResponse, $shipmentRequest);
        $shipmentResponse = $this->tagServiceOptions($shipmentResponse, $shipmentRequest);

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
        foreach ($pieceResponses as $labelId => $pieceResponse) {
            /** @var Piece $piece */
            $piece = $this->pieceFactory->create();
            $piece->addData([
                'label_id'          => $pieceResponse->labelId,
                'tracker_code'      => $pieceResponse->trackerCode,
                'postal_code'       => $pieceResponse->postalCode,
                'parcel_type'       => $pieceResponse->parcelType,
                'piece_number'      => $pieceResponse->pieceNumber,
                'label_type'        => $pieceResponse->labelType,
                'is_return'         => $isReturn,
                'shipment_request'  => $pieceResponse->shipmentRequest,
                'service_options'   => $pieceResponse->serviceOptions,
                'country_code'      => $pieceResponse->countryCode
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

    public function hideShipper($storeId, $shipmentRequest)
    {
        $hideShipperAddress = $this->getShipperAddress($storeId, 'hide_shipper');
        $shipmentRequest->onBehalfOf = $hideShipperAddress;

        return $shipmentRequest;
    }

    public function addReference($shipmentRequest, $reference)
    {
        $shipmentRequest->options[] = ['key' => 'REFERENCE', 'input' => $reference];

        return $shipmentRequest;
    }

    protected function tagShipmentRequest(ShipmentResponse $shipmentResponse, $shipmentRequest)
    {
        if ($this->helper->getConfigData('debug/enabled')
            && $this->helper->getConfigData('debug/save_label_requests')
        ) {
            if (!empty($shipmentResponse) && !empty($shipmentResponse->pieces)) {
                $updatedPieces = [];
                foreach ($shipmentResponse->pieces as $piece) {
                    $piece->shipmentRequest = json_encode($shipmentRequest->toArray());
                    $updatedPieces[] = $piece;
                }
                $shipmentResponse->pieces = $updatedPieces;
            }
        }
        return $shipmentResponse;
    }

    protected function tagServiceOptions(ShipmentResponse $shipmentResponse, $shipmentRequest)
    {
        if (!empty($shipmentResponse) && !empty($shipmentResponse->pieces)) {
            $serviceOptions = [];
            foreach ($shipmentRequest->options as $option) {
                $serviceOptions[] = $option->key;
            }

            $serviceOptions = array_unique($serviceOptions);

            $updatedPieces = [];
            foreach ($shipmentResponse->pieces as $piece) {
                $piece->serviceOptions = implode(',', $serviceOptions);
                $updatedPieces[] = $piece;
            }
            $shipmentResponse->pieces = $updatedPieces;
        }

        return $shipmentResponse;
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

    protected function tagCountryCode(ShipmentResponse $shipmentResponse, $countryCode)
    {
        if (!empty($shipmentResponse) && !empty($shipmentResponse->pieces)) {
            $updatedPieces = [];
            foreach ($shipmentResponse->pieces as $piece) {
                $piece->countryCode = strtoupper(trim($countryCode));
                $updatedPieces[] = $piece;
            }
            $shipmentResponse->pieces = $updatedPieces;
        }
        return $shipmentResponse;
    }

    /**
     * @param $storeId
     * @param string $group
     * @return Addressee
     */
    protected function getShipperAddress($storeId, $group = '')
    {
        if ($group) {
            $group .= '/';
        }

        /** @var Addressee $addressee */
        $addressee = $this->addresseeFactory->create(['automap' => [
            'name'        => [
                'firstName'   => $this->helper->getConfigData('shipper/' . $group . 'first_name', $storeId),
                'lastName'    => $this->helper->getConfigData('shipper/' . $group . 'last_name', $storeId),
                'companyName' => $this->helper->getConfigData('shipper/' . $group . 'company_name', $storeId),
            ],
            'address'     => [
                'countryCode' => $this->helper->getConfigData('shipper/country_code', $storeId),
                'postalCode'  => strtoupper($this->helper->getConfigData('shipper/' . $group . 'postal_code', $storeId)),
                'city'        => $this->helper->getConfigData('shipper/' . $group . 'city', $storeId),
                'street'      => $this->helper->getConfigData('shipper/' . $group . 'street', $storeId),
                'number'      => $this->helper->getConfigData('shipper/' . $group . 'house_number', $storeId),
                'isBusiness'  => true,
                'addition'    => $this->helper->getConfigData('shipper/' . $group . 'house_number_addition', $storeId),
            ],
            'email'       => $this->helper->getConfigData('shipper/' . $group . 'email', $storeId),
            'phoneNumber' => $this->helper->getConfigData('shipper/' . $group . 'phone', $storeId),
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

    /**
     * @param Address $address
     * @param array $street
     * @return Address
     */
    protected function updateAddressStreet(Address $address, array $street)
    {
        $fullStreet = implode(' ', $street);

        $data = $this->parseStreetData($fullStreet);
        $address->street = $data['street'];
        $address->number = $data['number'];
        $address->addition = $data['addition'];

        return $address;
    }

    /**
     * @param $raw
     * @return array [
     *      'street'   => (string) Parsed street $raw
     *      'number'   => (string) Parsed number from $raw
     *      'addition' => (string) Parsed additional street data from $raw
     * ]
     */
    protected function parseStreetData($raw)
    {
        $skipAdditionCheck = false;

        //if first word has ONE numbers and letter(s)
        $rawParts = explode(" ", trim($raw));
        $streetPrefix = '';
        $streetFirstWord = reset($rawParts);

        preg_match('/[0-9]+[a-zA-Z]+/i', trim($streetFirstWord), $firstWordParts);
        if (!empty($firstWordParts)) {
            $streetPrefix = $streetFirstWord . " ";
            unset($rawParts[key($rawParts)]);
        }

        $raw = implode(" ", $rawParts);

        preg_match('/([^0-9]*)\s*(.*)/i', trim($raw), $streetParts);
        $data = [
            'street' => isset($streetParts[1]) ? trim($streetParts[1]) : '',
            'number' => isset($streetParts[2]) ? trim($streetParts[2]) : '',
            'addition' => '',
        ];

        // Check if $street is empty
        if (strlen($data['street']) === 0) {
            // Try a reverse parse
            preg_match('/([\d]+[\w.-]*)\s*(.*)/i', trim($raw), $streetParts);
            $data['street'] = isset($streetParts[2]) ? trim($streetParts[2]) : '';
            $data['number'] = isset($streetParts[1]) ? trim($streetParts[1]) : '';
            $skipAdditionCheck = true;
        }

        // Check if $number has numbers
        if (preg_match("/\d/", $data['number']) !== 1) {
            $data['street'] = trim($raw);
            $data['number'] = '';
        } elseif (!$skipAdditionCheck) {
            preg_match('/([\d]+)[ .-]*(.*)/i', $data['number'], $numberParts);
            $data['number'] = isset($numberParts[1]) ? trim($numberParts[1]) : '';
            $data['addition'] = isset($numberParts[2]) ? trim($numberParts[2]) : '';
        }

        // Reassemble street
        if (isset($data['street'])) {
            $data['street'] = $streetPrefix . $data['street'];
        }

        return $data;
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
