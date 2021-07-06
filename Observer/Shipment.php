<?php

namespace DHLParcel\Shipping\Observer;

use DHLParcel\Shipping\Model\Exception\FaultyServiceOptionException;
use DHLParcel\Shipping\Model\Exception\NoTrackException;
use DHLParcel\Shipping\Model\Service\Capability as CapabilityService;
use DHLParcel\Shipping\Model\Service\Label as LabelService;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Option;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\OptionFactory;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\Piece;
use DHLParcel\Shipping\Model\Data\Api\Request\Shipment\PieceFactory;
use DHLParcel\Shipping\Model\Service\Shipment as ShipmentService;
use DHLParcel\Shipping\Model\Service\Preset as PresetService;
use DHLParcel\Shipping\Model\Exception\LabelCreationException;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;

class Shipment implements \Magento\Framework\Event\ObserverInterface
{
    protected $capabilityService;
    protected $labelService;
    protected $optionFactory;
    protected $orderRepository;
    protected $pieceFactory;
    protected $presetService;
    protected $request;
    protected $shipmentService;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CapabilityService $capabilityService,
        LabelService $labelService,
        OptionFactory $optionFactory,
        PieceFactory $pieceFactory,
        PresetService $presetService,
        RequestInterface $request,
        ShipmentService $shipmentService
    ) {
        $this->capabilityService = $capabilityService;
        $this->labelService = $labelService;
        $this->optionFactory = $optionFactory;
        $this->orderRepository = $orderRepository;
        $this->pieceFactory = $pieceFactory;
        $this->presetService = $presetService;
        $this->request = $request;
        $this->shipmentService = $shipmentService;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return \Magento\Sales\Api\Data\ShipmentInterface|\Magento\Sales\Model\Order\Shipment|void
     * @throws FaultyServiceOptionException
     * @throws LabelCreationException
     * @throws LocalizedException
     * @throws NoTrackException
     * @throws \DHLParcel\Shipping\Model\Exception\LabelNotFoundException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getData('shipment');

        // Return if addTrack can not be called
        if (!is_callable([$shipment, 'addTrack'])) {
            throw new NoTrackException(__("Unable to create DHL shipment because the shipment you are using does not support the ability to track it. Please contact your developer or use a different delivery method"));
        }

        if (!empty($this->request->getParam('shipment')['create_dhlparcel_shipping_label'])) {
            $tracks = $this->processForm($shipment->getOrderId());
        } elseif ($shipment->getData('dhlparcel_shipping_is_created')) {
            $order = $shipment->getOrder();
            $tracks = $this->processGrid($order);
        } else {
            return;
        }

        foreach ($tracks as $labelId => $track) {
            /** @var $track \Magento\Sales\Model\Order\Shipment\Track */
            $shipment->addTrack($track);

            // Fetching labels so they are cached
            $this->labelService->getLabelPdf($labelId);
        }

        return $shipment;
    }

    /**
     * @param \Magento\Sales\Model\Order $order $order
     * @return array
     * @throws FaultyServiceOptionException
     * @throws LabelCreationException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    protected function processGrid($order)
    {
        $toCountry = $order->getShippingAddress()->getCountryId();
        $storeId = $order->getStoreId();
        $toPostalCode = str_replace(' ', '', $order->getShippingAddress()->getPostcode());
        $toBusiness = $this->presetService->defaultToBusiness($storeId);
        $defaultOptions = $this->presetService->getDefaultOptions($order);
        $defaultOptions = $this->checkMailboxOverride($defaultOptions);
        $defaultOptions = $this->additionalServices($defaultOptions);

        $sizes = $this->capabilityService->getSizes($storeId, $toCountry, $toPostalCode, $toBusiness, array_keys($defaultOptions));

        if (empty($sizes) || !is_array($sizes)) {
            $skippableOptions = $this->presetService->filterSkippableDefaults($defaultOptions, $storeId);
            $requiredOptions = $this->presetService->getDefaultOptions($order, true);
            $requiredOptions = $this->checkMailboxOverride($requiredOptions);
            $requiredOptions = $this->additionalServices($requiredOptions);

            $options = $this->capabilityService->getOptions($storeId, $toCountry, $toPostalCode, $toBusiness, array_keys($requiredOptions));

            $allowedOptions = [];
            foreach ($skippableOptions as $skippableOption) {
                if (in_array($skippableOption, $options)) {
                    $allowedOptions[$skippableOption] = '';
                }
            }

            $defaultOptions = array_merge($requiredOptions, $allowedOptions);
            $sizes = $this->capabilityService->getSizes($storeId, $toCountry, $toPostalCode, $toBusiness, array_keys($defaultOptions));

            if (empty($sizes) || !is_array($sizes)) {
                $translations = $this->presetService->getTranslations();
                $translatedOptions = array_intersect_key($translations, $defaultOptions);
                throw new FaultyServiceOptionException(__('No DHL services could be found for this order with the selected service options: %1', implode(', ', $translatedOptions)));
            }
        }

        $packageKey = '';
        $packageWeight = 1000000;
        foreach ($sizes as $key => $package) {
            if (isset($package['minWeightKg']) && isset($package['maxWeightKg'])) {
                $packageSum = intval($package['minWeightKg']) + intval($package['maxWeightKg']);
            } else {
                $packageSum = 0;
            }
            if ($packageSum < $packageWeight) {
                $packageKey = $key;
                $packageWeight = $packageSum;
            }
        }

        $pieces = [$this->createPiece($packageKey)];

        $options = [];
        foreach ($defaultOptions as $optionKey => $optionValue) {
            $options[] = $this->createOption($optionKey, $optionValue);
        }

        $tracks = $this->shipmentService->create($order, $options, $pieces, $toBusiness);
        return $tracks;
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    protected function processForm($orderId)
    {
        if (!isset($this->request->getParam('dhlparcel')['shipment'])) {
            throw new LocalizedException(__('Shipment option label form not present'));
        }
        $shipmentFormData = $this->request->getParam('dhlparcel')['shipment'];
        if (!isset($shipmentFormData['method']) || empty($shipmentFormData['method'])) {
            throw new LocalizedException(__('No shipping method selected'));
        }

        $business = isset($shipmentFormData['audience']) && $shipmentFormData['audience'] === 'business';
        $options = [];
        $pieces = [];

        switch ($shipmentFormData['method']) {
            case 'PS':
                if (empty($shipmentFormData['method_options']['servicepoint_id'])) {
                    throw new LocalizedException(__('No ServicePoint selected'));
                }
                $options[] = $this->createOption(
                    $shipmentFormData['method'],
                    $shipmentFormData['method_options']['servicepoint_id']
                );
                break;
            default:
                $options[] = $this->createOption($shipmentFormData['method']);
                break;
        }

        if (isset($shipmentFormData['options'])) {
            foreach ($shipmentFormData['options'] as $key => $value) {
                if (isset($value['active']) && $key === $value['active']) {
                    $options[] = $this->createOption($key, isset($value['data']) ? $value['data'] : '');
                }
            }
        }

        if (isset($shipmentFormData['package'])) {
            foreach ($shipmentFormData['package'] as $key => $value) {
                $pieces[] = $this->createPiece($value);
            }
        }

        $order = $this->orderRepository->get($orderId);

        $tracks = $this->shipmentService->create($order, $options, $pieces, $business);
        return $tracks;
    }

    protected function checkMailboxOverride($options)
    {
        if ($this->request->getParam('method_override') == 'mailbox' && array_key_exists('DOOR', $options)) {
            unset($options['DOOR']);
            $options['BP'] = '';
        }
        return $options;
    }

    protected function additionalServices($options)
    {
        if ($this->request->getParam('service_saturday') == 'true' && !array_key_exists('S', $options)) {
            $options['S'] = '';
        }

        if ($this->request->getParam('service_sdd') == 'true' && !array_key_exists('SDD', $options)) {
            $options['SDD'] = '';
        }
        return $options;
    }

    /**
     * @param string $key
     * @param string $input
     * @return Option
     */
    protected function createOption($key, $input = '')
    {
        /** @var Option $option */
        $option = $this->optionFactory->create();
        $option->key = $key;
        if (strlen($input)) {
            $option->input = $input;
        }
        return $option;
    }

    /**
     * @param string $parcelType
     * @param int $quantity
     * @return Piece
     */
    protected function createPiece($parcelType, $quantity = 1)
    {
        /** @var Piece $piece */
        $piece = $this->pieceFactory->create();
        $piece->parcelType = $parcelType;
        $piece->quantity = $quantity;
        return $piece;
    }
}
