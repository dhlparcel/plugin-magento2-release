<?php

namespace DHLParcel\Shipping\Model\Service\Logic;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Api\Connector;
use DHLParcel\Shipping\Model\Exception\LabelCreationException;
use DHLParcel\Shipping\Model\Piece;
use DHLParcel\Shipping\Model\PieceFactory;
use DHLParcel\Shipping\Model\Service\Capability;
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
    protected $capabilityService;

    public function __construct(
        Connector                $connector,
        PieceFactory             $pieceFactory,
        UUIDFactory              $uuidFactory,
        ShipmentRequestFactory   $shipmentRequestFactory,
        AddresseeFactory         $addresseeFactory,
        OptionFactory            $optionFactory,
        ShipmentResponseFactory  $shipmentResponseFactory,
        PieceResource            $pieceResource,
        OrderRepositoryInterface $orderRepository,
        TrackFactory             $trackFactory,
        Data                     $helper,
        Capability               $capabilityService
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
        $this->capabilityService = $capabilityService;
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

        // Get REFERENCE and REFERENCE2 from shipment request
        $returnCapabilities = $this->capabilityService->getOptions(
            $storeId,
            $shipper->address->countryCode, // Use 'shipper', because we flip it in the capabilities
            $shipper->address->postalCode, // Use 'shipper', because we flip it in the capabilities
            true,
            [],
            true
        );

        // Return labels are at least DOOR
        $options = [$this->optionFactory->create(['automap' => ['key' => 'DOOR']])];
        $copyOptions = ['REFERENCE', 'REFERENCE2'];

        foreach ($shipmentRequest->options as $option) {
            if (!in_array($option->key, $copyOptions) || !array_key_exists($option->key, $returnCapabilities)) {
                continue;
            }

            $options[] = $option;
        }

        $shipmentRequest->options = $options;

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

    public function fakeRequest($shipmentRequest)
    {
        $response = [
            'shipmentId'     => $shipmentRequest->shipmentId,
            'product'        => 'DFY-B2C',
            'pieces'         => array(array(
                                          'labelId'     => uniqid('TEST-LABEL-ID-'),
                                          'trackerCode' => 'JVGL0' . rand(100000000000000000, 999999999999999999),
                                          'parcelType'  => 'SMALL',
                                          'pieceNumber' => 1,
                                          'labelType'   => 'B2X_Generic_A4_Third'
                                      )),
            'orderReference' => $shipmentRequest->orderReference,
            'deliveryArea'   => [
                'remote' => false,
                'type'   => 'NonRemote'
            ]
        ];

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
                'label_id'         => $pieceResponse->labelId,
                'tracker_code'     => $pieceResponse->trackerCode,
                'postal_code'      => $pieceResponse->postalCode,
                'parcel_type'      => $pieceResponse->parcelType,
                'piece_number'     => $pieceResponse->pieceNumber,
                'label_type'       => $pieceResponse->labelType,
                'is_return'        => $isReturn,
                'shipment_request' => $pieceResponse->shipmentRequest,
                'service_options'  => $pieceResponse->serviceOptions,
                'country_code'     => $pieceResponse->countryCode
            ]);

            $this->pieceResource->save($piece);

            /** @var Track $track */
            $track = $this->trackFactory->create([]);
            $track->addData([
                'carrier_code' => 'dhlparcel',
                'title'        => !$isReturn ? 'DHL eCommerce' : 'DHL eCommerce Return Label',
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

    /**
     * Check if an address string array, when joined, should be split into street, houseNumber and addition.
     * It should only be split if it starts or ends (but not both) with a digit. And only if the array contains only one number.
     *
     * @param array $strings
     * @return bool
     */
    public function shouldSplitAddress(array $strings): bool
    {
        $joined = trim(implode(' ', $strings));
        preg_match_all('/\d+/', $joined, $numberMatches);

        $hasExactlyOneNumber = count($numberMatches[0]) === 1;
        $hasNumberAtStart = preg_match('/^(\d+)\s+(.+)$/', $joined) === 1;
        $hasNumberAtEnd = preg_match('/^(.+?)\s+(\d+)$/', $joined) === 1;

        return $hasExactlyOneNumber && ($hasNumberAtStart xor $hasNumberAtEnd);
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
                'postalCode'  => strtoupper($this->helper->getConfigData('shipper/' . $group . 'postal_code', $storeId) ?? ''),
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

        if ($this->shouldSplitAddress($street)) {
            $data = $this->parseStreetData($fullStreet);
            $address->street = $data['street'];
            $address->number = $data['number'];
            $address->addition = $data['addition'];
        } else {
            $address->street = $fullStreet;
            $address->number = '';
            $address->addition = '';
        }

        return $address;
    }

    /**
     * @param $raw
     * @param $countryCode
     * @return array [
     *      'street'   => (string) Parsed street $raw
     *      'number'   => (string) Parsed number from $raw
     *      'addition' => (string) Parsed additional street data from $raw
     * ]
     */
    protected function parseStreetData($raw)
    {
        $skipAdditionCheck = false;
        $skipReverseCheck = false;
        $cutout = '';

        // Cutout starting special numbers from regular parsing logic
        $parsableParts = explode(' ', trim($raw), 2);

        // Check if it has a number with letters
        if (preg_match('/[0-9]+[a-z]+/i', reset($parsableParts)) === 1) {
            $cutout = reset($parsableParts) . ' ';
            $skipReverseCheck = true;
            unset($parsableParts[0]);

            // Check if it has a number with more than just letters, but also other available numbers
        } elseif (preg_match('/[0-9]+[^0-9]+/', reset($parsableParts)) === 1 && preg_match('/\d/', end($parsableParts)) === 1) {
            $cutout = reset($parsableParts) . ' ';
            $skipReverseCheck = true;
            unset($parsableParts[0]);

            // Check if it has something before a number
        } elseif (preg_match('/[^0-9]+[0-9]+/', reset($parsableParts)) === 1) {
            $cutout = reset($parsableParts) . ' ';
            $skipReverseCheck = true;
            unset($parsableParts[0]);

            // Check if starts with number (with anything), but also has numbers in the rest of the address
        } elseif (preg_match('/[^0-9]*[0-9]+[^0-9]*/', reset($parsableParts)) === 1 && preg_match('/\d/', end($parsableParts)) === 1) {
            $cutout = reset($parsableParts) . ' ';
            $skipReverseCheck = true;
            unset($parsableParts[0]);
        }

        $parsableStreet = implode(' ', $parsableParts);

        preg_match('/([^0-9]*)\s*(.*)/', trim($parsableStreet), $streetParts);
        $address = [
            'street'   => isset($streetParts[1]) ? trim($streetParts[1]) : '',
            'number'   => isset($streetParts[2]) ? trim($streetParts[2]) : '',
            'addition' => '',
        ];

        // Check if $street is empty
        if (strlen($address['street']) === 0 && !$skipReverseCheck) {
            // Try a reverse parse
            preg_match('/([\d]+[\w.-]*)\s*(.*)/i', trim($parsableStreet), $streetParts);
            $address['street'] = isset($streetParts[2]) ? trim($streetParts[2]) : '';
            $address['number'] = isset($streetParts[1]) ? trim($streetParts[1]) : '';
            $skipAdditionCheck = true;
        }

        // Check if $number has no numbers
        if (preg_match('/\d/', $address['number']) === 0) {
            $address['street'] = trim($parsableStreet);
            $address['number'] = '';

            // Addition check
        } elseif (!$skipAdditionCheck) {
            // If there are no letters, but has additional spaced numbers, use last number as number, no addition
            preg_match('/([^a-z]+)\s+([\d]+)$/i', $address['number'], $number_parts);
            if (isset($number_parts[2])) {
                $address['street'] .= ' ' . $number_parts[1];
                $address['number'] = $number_parts[2];

                // Regular number / addition split
            } else {
                preg_match('/([\d]+)[ .-]*(.*)/i', $address['number'], $number_parts);
                $address['number'] = isset($number_parts[1]) ? trim($number_parts[1]) : '';
                $address['addition'] = isset($number_parts[2]) ? trim($number_parts[2]) : '';
            }
        }

        // Reassemble street
        if (isset($address['street'])) {
            $address['street'] = $cutout . $address['street'];
        }

        // Be sure these fields are filled
        $address['number'] = isset($address['number']) ? $address['number'] : '';
        $address['addition'] = isset($address['addition']) ? $address['addition'] : '';

        // Clean any starting punctuations
        preg_match('/^[[:punct:]\s]+(.*)/', $address['street'], $cleanStreet);
        if (isset($cleanStreet[1])) {
            $address['street'] = $cleanStreet[1];
        }
        preg_match('/^[[:punct:]\s]+(.*)/', $address['number'], $cleanNumber);
        if (isset($cleanNumber[1])) {
            $address['number'] = $cleanNumber[1];
        }
        preg_match('/^[[:punct:]\s]+(.*)/', $address['addition'], $cleanAddition);
        if (isset($cleanAddition[1])) {
            $address['addition'] = $cleanAddition[1];
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
