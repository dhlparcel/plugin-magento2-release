<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Data\DeliveryServicesAvailabilityFactory;
use DHLParcel\Shipping\Model\Data\Capability\OptionFactory;
use Psr\Log\LoggerInterface;

class DeliveryServices
{
    const NO_NEIGHBOUR = 'NBB';
    const EVENING = 'EVE';
    const MORNING = 'EXP';
    const SATURDAY = 'S';

    protected $assetRepository;
    protected $priceHelper;
    protected $taxHelper;
    protected $helper;
    protected $availabilityFactory;
    protected $optionFactory;
    protected LoggerInterface $logger;

    protected $availableOptions = [
        self::NO_NEIGHBOUR,
        self::EVENING,
        self::MORNING,
        self::SATURDAY,
    ];

    public function __construct(
        \Magento\Framework\View\Asset\Repository $assetRepository,
        \Magento\Framework\Pricing\Helper\Data   $priceHelper,
        \Magento\Tax\Helper\Data                 $taxHelper,
        Data                                     $helper,
        DeliveryServicesAvailabilityFactory      $availabilityFactory,
        OptionFactory                            $optionFactory,
        LoggerInterface                          $logger
    ) {
        $this->assetRepository = $assetRepository;
        $this->priceHelper = $priceHelper;
        $this->taxHelper = $taxHelper;
        $this->helper = $helper;
        $this->availabilityFactory = $availabilityFactory;
        $this->optionFactory = $optionFactory;
        $this->logger = $logger;
    }

    public function getToBusiness()
    {
        return boolval($this->helper->getConfigData('label/default_to_business'));
    }

    /**
     * @param $options
     * @param int $subtotal
     * @param array $selections
     * @return \DHLParcel\Shipping\Model\Data\DeliveryServicesAvailability
     */
    public function getAvailability($options, $subtotal = 0, $selections = [], $store = null)
    {
        /** @var \DHLParcel\Shipping\Model\Data\DeliveryServicesAvailability $availability */
        $availability = $this->availabilityFactory->create();

        // Services data
        $availability->serviceData = [];
        if (array_key_exists(self::NO_NEIGHBOUR, $options) &&
            $this->isEnabled(self::NO_NEIGHBOUR)) {
            $availability->serviceData[] = $this->getServiceData(
                self::NO_NEIGHBOUR,
                $this->getTitle(self::NO_NEIGHBOUR),
                __('Do not drop off at neighbours'),
                $subtotal,
                $store
            );
        }
        if (array_key_exists(self::EVENING, $options) &&
            $this->isEnabled(self::EVENING, $options)) {
            $availability->serviceData[] = $this->getServiceData(
                self::EVENING,
                $this->getTitle(self::EVENING),
                __('Delivery in the evening'),
                $subtotal,
                $store
            );
        }
        if (array_key_exists(self::SATURDAY, $options) &&
            $this->isEnabled(self::SATURDAY)) {
            $availability->serviceData[] = $this->getServiceData(
                self::SATURDAY,
                $this->getTitle(self::SATURDAY),
                __('Package will also be delivered on Saturdays'),
                $subtotal,
                $store
            );
        }
        if (array_key_exists(self::MORNING, $options) &&
            $this->isEnabled(self::MORNING)) {
            $availability->serviceData[] = $this->getServiceData(
                self::MORNING,
                $this->getTitle(self::MORNING),
                __('Deliver shipment before 11 AM'),
                $subtotal,
                $store
            );
        }

        // Enabled
        $availability->enabled = boolval(count($availability->serviceData) > 0);

        // Selections
        $selectableServices = array_column($availability->serviceData, 'value');
        $selectedServicesSanitized = $this->sanitizeData($selections);
        $availability->selectedServices = array_values(
            array_intersect($selectedServicesSanitized, $selectableServices)
        );

        $this->logger->info('DeliveryServices getAvailability', [
            'selectableServices'        => $selectableServices,
            'selectedServicesSanitized' => $selectedServicesSanitized,
            'selectedServices'          => $availability->selectedServices,
        ]);

        // Exclusions
        $exclusions = [];
        foreach ($options as $key => $option) {
            $exclusions[$key] = $option['exclusions'];
        }
        $availability->excludedServiceData = $exclusions;

        return $availability;
    }

    /**
     * @return array
     */
    public function getAllowedOptions()
    {
        return $this->filterAllowedOnly($this->availableOptions);
    }

    public function filterAllowedOnly($options)
    {
        $options = $this->sanitizeData($options);
        $filtered = [];
        foreach ($options as $option) {
            if ($this->isEnabled($option)) {
                $filtered[] = $option;
            }
        }
        return $filtered;
    }

    /**
     * @param $option
     * @return bool
     */
    public function isEnabled($option)
    {
        if ($option === self::NO_NEIGHBOUR) {
            return boolval($this->helper->getConfigData('shipping_methods/door/service_no_neighbour_enabled'));
        } elseif ($option === self::EVENING) {
            return boolval($this->helper->getConfigData('shipping_methods/door/service_evening_enabled'));
        } elseif ($option === self::SATURDAY) {
            return boolval($this->helper->getConfigData('shipping_methods/door/service_saturday_enabled'));
        } elseif ($option === self::MORNING) {
            return boolval($this->helper->getConfigData('shipping_methods/door/service_morning_enabled'));
        }
        return false;
    }

    public function serviceCost($subtotal, $options)
    {
        $options = $this->sanitizeData($options);
        $cost = 0;
        foreach ($options as $option) {
            if ($option === self::NO_NEIGHBOUR) {
                $limit = $this->helper->getConfigData('shipping_methods/door/service_no_neighbour/limit');
                if ($limit === '' || $limit === null || $subtotal <= $limit) {
                    $cost += $this->helper->getConfigData('shipping_methods/door/service_no_neighbour/cost');
                }
            } elseif ($option === self::EVENING) {
                $limit = $this->helper->getConfigData('shipping_methods/door/service_evening/limit');
                if ($limit === '' || $limit === null || $subtotal <= $limit) {
                    $cost += $this->helper->getConfigData('shipping_methods/door/service_evening/cost');
                }
            } elseif ($option === self::SATURDAY) {
                $limit = $this->helper->getConfigData('shipping_methods/door/service_saturday/limit');
                if ($limit === '' || $limit === null || $subtotal <= $limit) {
                    $cost += $this->helper->getConfigData('shipping_methods/door/service_saturday/cost');
                }
            } elseif ($option === self::MORNING) {
                $limit = $this->helper->getConfigData('shipping_methods/door/service_morning/limit');
                if ($limit === '' || $limit === null || $subtotal <= $limit) {
                    $cost += $this->helper->getConfigData('shipping_methods/door/service_morning/cost');
                }
            }
        }
        return $cost;
    }

    public function saveSelection($order, $services)
    {
        if (empty($order) || !$order instanceof \Magento\Sales\Api\Data\OrderInterface) {
            return;
        }

        if (empty($services) || !is_array($services)) {
            return;
        }

        $selection = $this->sanitizeData($services);

        $order->setData('dhlparcel_shipping_deliveryservices_selection', json_encode($selection));
    }

    public function getSelection($order, $asKeys = false)
    {
        if (empty($order) || !$order instanceof \Magento\Sales\Api\Data\OrderInterface) {
            return [];
        }

        $selectionJson = $order->getData('dhlparcel_shipping_deliveryservices_selection');

        if (empty($selectionJson)) {
            return [];
        }

        $selectionData = json_decode($selectionJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $selectionData = $this->sanitizeData($selectionData);

        if ($asKeys) {
            $selectionData = array_flip($selectionData);
            $selectionData = array_fill_keys(array_keys($selectionData), '');
        }

        return $selectionData;
    }

    /**
     * @param $services
     * @return array
     */
    public function getTitles($services)
    {
        $services = $this->sanitizeData($services);
        $titles = [];
        foreach ($services as $service) {
            $title = $this->getTitle($service);
            if ($title) {
                $titles[] = $title;
            }
        }
        return $titles;
    }

    protected function getTitle($key)
    {
        $titles = [
            self::NO_NEIGHBOUR => __('No neighbour'),
            self::EVENING      => __('Evening'),
            self::SATURDAY     => __('Saturday delivery'),
            self::MORNING      => __('Morning'),
        ];

        if (!array_key_exists($key, $titles)) {
            return '';
        }

        return $titles[$key];
    }

    protected function getServiceData($code, $title, $description, $subtotal, $store = null)
    {
        $price = $this->getTaxPrice($this->serviceCost($subtotal, [$code]), $store);

        return [
            'value'       => $code,
            'title'       => $title,
            'description' => $description,
            'image'       => $this->getImageUrl($code),
            'price'       => $price,
        ];
    }

    protected function getTaxPrice($serviceCost, $store = null)
    {
        if ($this->taxHelper->getShippingPriceDisplayType($store) === \Magento\Tax\Model\Config::DISPLAY_TYPE_EXCLUDING_TAX) {
            return '+ ' . $this->priceHelper->currencyByStore($serviceCost, $store, true, false);
        }

        return '+ ' . $this->priceHelper->currencyByStore($this->taxHelper->getShippingPrice($serviceCost, true, null, null, $store), $store, true, false);
    }

    protected function sanitizeData($array)
    {
        $sanitized = [];
        if (is_array($array) && count($array) > 0) {
            foreach ($array as $item) {
                if (in_array($item, $this->availableOptions)) {
                    $sanitized[] = strtoupper($item ?? '');
                }
            }
        }
        return $sanitized;
    }

    protected function getImageUrl($code)
    {
        return $this->assetRepository->getUrl('DHLParcel_Shipping::images/options/' . strtolower($code) . '.svg');
    }
}
