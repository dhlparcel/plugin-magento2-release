<?php

namespace DHLParcel\Shipping\Model;

use DHLParcel\Shipping\Logger\DebugLogger;
use DHLParcel\Shipping\Model\Config\Source\RateConditions;
use DHLParcel\Shipping\Model\Config\Source\RateMethod;
use DHLParcel\Shipping\Model\PieceFactory;
use DHLParcel\Shipping\Model\ResourceModel\Carrier\RateManager;
use DHLParcel\Shipping\Model\ResourceModel\Piece as PieceResource;
use DHLParcel\Shipping\Model\Service\Capability as CapabilityService;
use DHLParcel\Shipping\Model\Service\CartService;
use DHLParcel\Shipping\Model\Service\DeliveryServices as DeliveryServicesService;
use DHLParcel\Shipping\Model\Service\DeliveryTimes as DeliveryTimesService;
use DHLParcel\Shipping\Model\Service\Preset as PresetService;
use DHLParcel\Shipping\Model\Service\ServicePoint as ServicePointService;
use Magento\Checkout\Model\Session as CheckoutSession;

class Carrier extends \Magento\Shipping\Model\Carrier\AbstractCarrierOnline implements \Magento\Shipping\Model\Carrier\CarrierInterface
{
    // Attributes are restricted to Mage_Eav_Model_Entity_Attribute::ATTRIBUTE_CODE_MAX_LENGTH = 30 max characters
    const BLACKLIST_GENERAL = 'dhlparcel_blacklist';
    const BLACKLIST_SERVICEPOINT = 'dhlparcel_blacklist_sp';
    const BLACKLIST_ALL = 'dhlparcel_blacklist_all';
    protected $_code = 'dhlparcel';
    protected $capabilityService;
    protected $cartService;
    protected $checkoutSession;
    protected $debugLogger;
    protected $defaultConditionName;
    protected $pieceFactory;
    protected $pieceResource;
    protected $deliveryTimesService;
    protected $deliveryServicesService;
    protected $presetService;
    protected $rateManager;
    protected $servicePointService;
    protected $storeManager;
    protected $trackingUrl = 'https://my.dhlparcel.nl/home/tracktrace/{{trackerCode}}?lang={{locale}}';
    protected $alternateUrls = [
        'BE' => 'https://www.dhlparcel.be/en/consumer/track-your-shipment?tc={{trackerCode}}&pc={{postalCode}}&lc={{locale}}'
    ];

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
        DeliveryServicesService $deliveryServicesService,
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
        $this->deliveryServicesService = $deliveryServicesService;
        $this->presetService = $presetService;
        $this->rateManager = $rateManager;
        $this->servicePointService = $servicePointService;
        $this->storeManager = $storeManager;
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
        $blacklist = $this->createBlacklist($request->getAllItems());
        if ($blacklist === true) {
            return $result;
        }
        foreach ($this->getMethods() as $key => $title) {
            $method = $this->getShippingMethod($request, $key, $blacklist);
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
     * @param $blacklist
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getShippingMethod(
        \Magento\Quote\Model\Quote\Address\RateRequest $request,
        $methodKey,
        $blacklist
    ) {
        $this->debugLogger->info("CARRIER starting get shipping method $methodKey");
        if (!$this->getConfigData('shipping_methods/' . $methodKey . '/enabled')) {
            $this->debugLogger->info("CARRIER method $methodKey disabled");
            return null;
        }

        if ($methodKey === 'sameday') {
            $showSameday = $this->deliveryTimesService->showSameday();
            $showSamedayAfterCutoff = $this->deliveryTimesService->showSamedayAfterCutoff();
            if (!$showSameday && !$showSamedayAfterCutoff) {
                $this->debugLogger->info("CARRIER same day delivery hidden");
                return null;
            }
        }

        $presetOptions = $this->presetService->getOptions($methodKey);

        foreach ($blacklist as $blacklistOption) {
            if (array_key_exists($blacklistOption, $presetOptions)) {
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

        // Calculate service costs
        $serviceCost = 0;
        if ($methodKey === 'door') {
            $deliveryServices = $this->checkoutSession->getDHLParcelShippingDeliveryServices();
            $deliveryServices = $this->deliveryServicesService->filterAllowedOnly($deliveryServices);
            $serviceCost = $this->deliveryServicesService->serviceCost($request->getPackageValueWithDiscount(), $deliveryServices);
        }

        /* @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();
        // Set variable or fixed price
        $rateMethod = $this->getConfigData('shipping_methods/' . $methodKey . '/rate_method');
        $this->debugLogger->info("CARRIER method $methodKey variable rate calculation: $rateMethod");
        if ($rateMethod == RateMethod::METHOD_VARIABLE_RATE) {
            $conditionName = $this->getConfigData('shipping_methods/' . $methodKey . '/rate_condition');
            $request->setConditionName($conditionName ? $conditionName : $this->defaultConditionName);

            // Use discounted price to send a rate request if setting enabled
            if (boolval($this->getConfigData('usability/discount_after_coupon/enabled'))) {
                $request->setPackageValue($request->getPackageValueWithDiscount());
            }
            if (!$rate = $this->rateManager->getRate($request, $methodKey)) {
                $this->debugLogger->info("CARRIER method $methodKey no variable rates found");
                return null;
            }

            $method->setPrice($rate['price'] + $serviceCost);
            $method->setCost($rate['cost']);
        } else {
            $method->setPrice($this->getConfigData('shipping_methods/' . $methodKey . '/price') + $serviceCost);
        }

        if ($request->getFreeShipping() === true || $request->getPackageQty() == $this->cartService->getFreeBoxesCount($request)) {
            if (boolval($this->getConfigData('usability/discount_after_coupon/always_charge_servicecosts'))) {
                $method->setPrice($serviceCost);
            } else {
                $method->setPrice('0.00');
            }
        }

        $title = $this->getConfigData('title');
        $titleKey = 'title';
        if ($methodKey === 'sameday' && $this->deliveryTimesService->showSamedayAfterCutoff()) {
            $titleKey = 'title_after_cutoff';
        }

        $method->setCarrier($this->getCarrierCode());
        $method->setCarrierTitle(__($title));

        $method->setMethod($methodKey);
        $method->setMethodTitle($this->getMethodTitle($request, $methodKey, $titleKey));

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

    protected function getMethodTitle(\Magento\Quote\Model\Quote\Address\RateRequest $request, $key, $titleKey = 'title')
    {
        // Default
        $title = $this->getConfigData('shipping_methods/' . $key . '/' . $titleKey);
        if ($titleKey !== 'title' && empty($title)) {
            $title = $this->getConfigData('shipping_methods/' . $key . '/title');
        }
        if (empty($title)) {
            $title = $this->getMethods($key);
        }

        // Update title if using services
        if ($key == 'door') {
            $deliveryServices = $this->checkoutSession->getDHLParcelShippingDeliveryServices();
            $deliveryServices = $this->deliveryServicesService->filterAllowedOnly($deliveryServices);
            if (isset($deliveryServices) && is_array($deliveryServices)) {
                if (!in_array('DOOR', $deliveryServices)) {
                    array_push($deliveryServices, 'DOOR');
                }

                $search = $this->presetService->searchMethodKey($deliveryServices);
                if ($search) {
                    $title = $this->getConfigData('shipping_methods/' . $search . '/title');
                    if (empty($title)) {
                        $title = $this->getMethods($search);
                    }
                } else {
                    $titleCollection = $this->deliveryServicesService->getTitles($deliveryServices);
                    array_unshift($titleCollection, $title);
                    $title = implode(' + ', $titleCollection);
                }
            }
        }

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

        $locale = 'en-NL';
        if ($this->getConfigData('label/alternative_tracking/enabled')) {
            $trackingUrl = $this->getConfigData('label/alternative_tracking/url');
        } else {
            if ($piece->getCountryCode() === 'NL') {
                $locale = 'nl-NL';
            } elseif ($piece->getCountryCode() === 'BE') {
                $locale = 'en-BE';
            }

            $trackingUrl = $this->trackingUrl;
            if (array_key_exists($piece->getCountryCode(), $this->alternateUrls)) {
                $trackingUrl = $this->alternateUrls[$piece->getCountryCode()];
            }
        }

        $postalCode = $piece->getPostalCode();
        $search = ['{{trackerCode}}', '{{postalCode}}', '{{locale}}'];
        $replace = [$trackerCode, $postalCode, $locale];
        return str_replace($search, $replace, $trackingUrl);
    }

    /**
     * @param null|string $key
     * @return array|string|null
     */
    protected function getMethods($key = null)
    {
        $methods = [
            PresetService::SHIPPING_METHOD_DOOR                 => __('Standard delivery'),
            PresetService::SHIPPING_METHOD_EVENING              => __('Evening delivery'),
            PresetService::SHIPPING_METHOD_NO_NEIGHBOUR         => __('No neighbour delivery'),
            PresetService::SHIPPING_METHOD_NO_NEIGHBOUR_EVENING => __('No neighbour and evening delivery'),
            PresetService::SHIPPING_METHOD_SATURDAY             => __('Saturday delivery'),
            PresetService::SHIPPING_METHOD_SERVICE_POINT        => __('ServicePoint'),
            PresetService::SHIPPING_METHOD_MORNING              => __('Morning delivery'),
            PresetService::SHIPPING_METHOD_SAMEDAY              => __('Same-day delivery'),
        ];
        if (is_string($key)) {
            if (array_key_exists($key, $methods)) {
                return $methods[$key];
            }
            return null;
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
     * @return array|bool
     */
    protected function createBlacklist($items)
    {
        $blacklist = [];

        foreach ($items as $item) {
            /** @var \Magento\Quote\Model\Quote\Item $item **/
            $product = $item->getProduct();
            if ($product[self::BLACKLIST_ALL]) {
                return true;
            }

            if ($product[self::BLACKLIST_SERVICEPOINT]) {
                $blacklist[] = 'PS';
            }

            if (!empty($product[self::BLACKLIST_GENERAL]) && $product[self::BLACKLIST_GENERAL] !== null) {
                foreach (explode(',', $product[self::BLACKLIST_GENERAL]) as $option) {
                    $blacklist[] = $option;
                }
            }
        }
        return array_unique($blacklist);
    }
}
