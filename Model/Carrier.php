<?php

namespace DHLParcel\Shipping\Model;

use DHLParcel\Shipping\Logger\DebugLogger;
use Magento\Checkout\Model\Session as CheckoutSession;
use DHLParcel\Shipping\Model\Service\Capability as CapabilityService;
use DHLParcel\Shipping\Model\Service\CartService;
use DHLParcel\Shipping\Model\PieceFactory;
use DHLParcel\Shipping\Model\ResourceModel\Piece as PieceResource;
use DHLParcel\Shipping\Model\Service\DeliveryTimes as DeliveryTimesService;
use DHLParcel\Shipping\Model\Service\Preset as PresetService;
use DHLParcel\Shipping\Model\ResourceModel\Carrier\RateManager;
use DHLParcel\Shipping\Model\Service\ServicePoint as ServicePointService;

use DHLParcel\Shipping\Model\Config\Source\RateConditions;
use DHLParcel\Shipping\Model\Config\Source\RateMethod;

class Carrier extends \Magento\Shipping\Model\Carrier\AbstractCarrierOnline implements \Magento\Shipping\Model\Carrier\CarrierInterface
{
    // Attributes are restricted to Mage_Eav_Model_Entity_Attribute::ATTRIBUTE_CODE_MAX_LENGTH = 30 max characters
    const BLACKLIST_GENERAL = 'dhlparcel_blacklist';
    const BLACKLIST_SERVICEPOINT = 'dhlparcel_blacklist_sp';
    protected $_code = 'dhlparcel';
    protected $capabilityService;
    protected $cartService;
    protected $checkoutSession;
    protected $debugLogger;
    protected $defaultConditionName;
    protected $pieceFactory;
    protected $pieceResource;
    protected $deliveryTimesService;
    protected $presetService;
    protected $rateManager;
    protected $servicePointService;
    protected $storeManager;
    protected $trackingUrl = 'https://www.dhlparcel.nl/nl/volg-uw-zending?tc={{trackerCode}}&pc={{postalCode}}';

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Xml\Security $xmlSecurity,
        \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        CheckoutSession $checkoutSession,
        CapabilityService $capabilityService,
        CartService $cartService,
        DebugLogger $debugLogger,
        PieceFactory $pieceFactory,
        PieceResource $pieceResource,
        DeliveryTimesService $deliveryTimesService,
        PresetService $presetService,
        RateManager $rateManager,
        ServicePointService $servicePointService,
        array $data = []
    ) {
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );

        $this->checkoutSession = $checkoutSession;
        $this->capabilityService = $capabilityService;
        $this->cartService = $cartService;
        $this->defaultConditionName = RateConditions::PACKAGE_VALUE;
        $this->debugLogger = $debugLogger;
        $this->pieceFactory = $pieceFactory;
        $this->pieceResource = $pieceResource;
        $this->deliveryTimesService = $deliveryTimesService;
        $this->presetService = $presetService;
        $this->rateManager = $rateManager;
        $this->servicePointService = $servicePointService;
        $this->storeManager = $storeManager;

        if ($this->getConfigData('label/alternative_tracking/enabled')) {
            $this->trackingUrl = $this->getConfigData('label/alternative_tracking/url');
        }
    }

    /**
     * The DHL Shipping shipping carrier does not calculate rates.
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return bool|\Magento\Framework\DataObject|\Magento\Shipping\Model\Rate\Result|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function collectRates(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        $this->debugLogger->info('CARRIER ### initiating collect rates', $request->toArray());
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateFactory->create();
        $blackList = $this->createBlackList($request->getAllItems());

        foreach ($this->getMethods() as $key => $title) {
            $method = $this->getShippingMethod($request, $key, $blackList);
            if ($method) {
                $this->debugLogger->info("CARRIER successfully added method $key to RateRequest");
                $result->append($method);
            } else {
                $this->debugLogger->info("CARRIER failed to add method $key to RateRequest");
            }
        }
        $this->debugLogger->info('CARRIER --- collect rates finished', $result->asArray());
        return $result;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @param $methodKey
     * @param $blackList
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getShippingMethod(\Magento\Quote\Model\Quote\Address\RateRequest $request, $methodKey, $blackList)
    {
        $this->debugLogger->info("CARRIER starting get shipping method $methodKey");
        if (!$this->getConfigData('shipping_methods/' . $methodKey . '/enabled')) {
            $this->debugLogger->info("CARRIER method $methodKey disabled");
            return null;
        }

        if ($methodKey === 'sameday') {
            $showSameday = $this->deliveryTimesService->showSameday();
            if (!$showSameday) {
                $this->debugLogger->info("CARRIER same day delivery hidden");
                return null;
            }
        }

        $presetOptions = $this->presetService->getOptions($methodKey);

        foreach ($blackList as $blackListedOption) {
            if (array_key_exists($blackListedOption, $presetOptions)) {
                return null;
            }
        }

        $toCountry = $request->getDestCountryId();
        $toPostalCode = $request->getDestPostcode();
        $toBusiness = $this->presetService->defaultToBusiness($this->storeManager->getStore()->getId());
        $requestOptions = array_keys($presetOptions);

        $sizes = $this->capabilityService->getSizes($this->storeManager->getStore()->getId(), $toCountry, $toPostalCode, $toBusiness, $requestOptions);
        if (empty($sizes)) {
            $this->debugLogger->info("CARRIER method $methodKey not available due to capabilities", ['options' => $requestOptions, 'response' => $sizes]);
            return null;
        }

        /* @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();
        // Set variable or fixed price
        $rateMethod = $this->getConfigData('shipping_methods/' . $methodKey . '/rate_method');
        $this->debugLogger->info("CARRIER method $methodKey variable rate calculation: $rateMethod");
        if ($rateMethod == RateMethod::METHOD_VARIABLE_RATE) {
            $conditionName = $this->getConfigData('shipping_methods/' . $methodKey . '/rate_condition');
            $request->setConditionName($conditionName ? $conditionName : $this->defaultConditionName);

            if (!$rate = $this->rateManager->getRate($request, $methodKey)) {
                $this->debugLogger->info("CARRIER method $methodKey no variable rates found");
                return null;
            }

            $method->setPrice($rate['price']);
            $method->setCost($rate['cost']);
        } else {
            $method->setPrice($this->getConfigData('shipping_methods/' . $methodKey . '/price'));
        }

        if ($request->getPackageQty() == $this->cartService->getFreeBoxesCount($request)) {
            $method->setPrice('0.00');
        }

        $title = $this->getConfigData('title');
        $method->setCarrier($this->getCarrierCode());
        $method->setCarrierTitle(__($title));

        $method->setMethod($methodKey);
        $method->setMethodTitle($this->getMethodTitle($request, $methodKey));

        return $method;
    }

    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        unset($request);
        return null;
    }

    public function getAllowedMethods()
    {
        return $this->getMethods();
    }

    public function getTracking($tracking)
    {
        $result = $this->_trackFactory->create();
        $title = $this->getConfigData('title');
        $trackingUrl = $this->getTrackingUrl($tracking);

        /** @var \Magento\Shipping\Model\Tracking\Result\Status $trackStatus */
        $trackStatus = $this->_trackStatusFactory->create();
        $trackStatus->setCarrier($this->_code);
        $trackStatus->setCarrierTitle(__($title));
        $trackStatus->setTracking($tracking);
        $trackStatus->setUrl($trackingUrl);
        $trackStatus->addData([]);
        $result->append($trackStatus);

        return $result;
    }

    protected function getMethodTitle(\Magento\Quote\Model\Quote\Address\RateRequest $request, $key)
    {
        $title = $this->getConfigData('shipping_methods/' . $key . '/title');

        if ($key == 'servicepoint') {
            $servicePointId = $this->checkoutSession->getDHLParcelShippingServicePointId();
            $servicePointCountry = $this->checkoutSession->getDHLParcelShippingServicePointCountry();
            $servicePointName = $this->checkoutSession->getDHLParcelShippingServicePointName();
            $servicePointPostcode = $this->checkoutSession->getDHLParcelShippingServicePointPostcode();

            // If country or postalcode doesn't match, reset
            if ($servicePointCountry != $request->getDestCountryId() || $servicePointPostcode != $request->getDestPostcode()) {
                $this->checkoutSession->setDHLParcelShippingServicePointId(null);
                $this->checkoutSession->setDHLParcelShippingServicePointCountry(null);
                $this->checkoutSession->setDHLParcelShippingServicePointName(null);
                $this->checkoutSession->setDHLParcelShippingServicePointPostcode(null);

                $servicePointId = null;
                $servicePointCountry = null;
                $servicePointName = null;
                $servicePointPostcode = null;
            }

            // Select default servicePoint if none is selected
            if (!$servicePointId) {
                $toCountry = $request->getDestCountryId();
                $toPostalCode = $request->getDestPostcode();
                $servicePoint = $this->servicePointService->getClosest($toPostalCode, $toCountry);
                if ($servicePoint) {
                    $servicePointId = $servicePoint->id;
                    $servicePointCountry = $servicePoint->country;
                    $servicePointName = $servicePoint->name;

                    $this->checkoutSession->setDHLParcelShippingServicePointId($servicePointId);
                    $this->checkoutSession->setDHLParcelShippingServicePointCountry($servicePointCountry);
                    $this->checkoutSession->setDHLParcelShippingServicePointName($servicePointName);
                    $this->checkoutSession->setDHLParcelShippingServicePointPostcode($toPostalCode);
                }
            }

            // Show ServicePoint if available
            if ($servicePointId) {
                $title = sprintf('%1$s: %2$s', $title, $servicePointName);
            }
        }

        return $title;
    }

    public function getTrackingUrl($trackerCode)
    {
        $piece = $this->pieceFactory->create();
        /** @var Piece $piece */
        $this->pieceResource->load($piece, $trackerCode, 'tracker_code');
        if (!$piece) {
            return false;
        }

        $postalCode = $piece->getPostalCode();
        $search = ['{{trackerCode}}', '{{postalCode}}'];
        $replace = [$trackerCode, $postalCode];
        return str_replace($search, $replace, $this->trackingUrl);
    }

    /**
     * @param null|string $key
     * @return array|string
     */
    protected function getMethods($key = null)
    {
        $methods = [
            PresetService::SHIPPING_METHOD_DOOR                 => __('Home delivery'),
            PresetService::SHIPPING_METHOD_EVENING              => __('Evening delivery'),
            PresetService::SHIPPING_METHOD_NO_NEIGHBOUR         => __('No neighbour delivery'),
            PresetService::SHIPPING_METHOD_NO_NEIGHBOUR_EVENING => __('No neighbour and evening delivery'),
            PresetService::SHIPPING_METHOD_SATURDAY             => __('Saturday delivery'),
            PresetService::SHIPPING_METHOD_SERVICE_POINT        => __('ServicePoint'),
            PresetService::SHIPPING_METHOD_MORNING              => __('Morning delivery'),
            PresetService::SHIPPING_METHOD_SAMEDAY              => __('Same-day delivery'),
        ];
        if (is_string($key)) {
            return $methods[$key];
        }
        return $methods;
    }

    public function isZipCodeRequired($countryId = null)
    {
        unset($countryId);
        return false;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item[] $items
     * @return array
     */
    protected function createBlackList($items)
    {
        $blackList = [];
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($items as $item) {
            $product = $item->getProduct();
            if ($product[self::BLACKLIST_SERVICEPOINT]) {
                $blackList[] = 'PS';
            }
            foreach (explode(',', $product[self::BLACKLIST_GENERAL]) as $option) {
                $blackList[] = $option;
            }
        }
        return array_unique($blackList);
    }
}
