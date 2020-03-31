<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Config\Source\ReferenceOptions;
use DHLParcel\Shipping\Model\Config\Source\ServiceOptionDefault;
use DHLParcel\Shipping\Model\Service\DeliveryServices as DeliveryServicesService;

class Preset
{
    const SHIPPING_METHOD_DOOR = 'door';
    const SHIPPING_METHOD_EVENING = 'evening';
    const SHIPPING_METHOD_SERVICE_POINT = 'servicepoint';
    const SHIPPING_METHOD_NO_NEIGHBOUR = 'no_neighbour';
    const SHIPPING_METHOD_NO_NEIGHBOUR_EVENING = 'no_neighbour_evening';
    const SHIPPING_METHOD_SATURDAY = 'saturday';
    const SHIPPING_METHOD_MORNING = 'morning';
    const SHIPPING_METHOD_SAMEDAY = 'sameday';

    protected $helper;
    protected $deliveryServicesService;

    public function __construct(
        Data $helper,
        DeliveryServicesService $deliveryServicesService
    ) {
        $this->helper = $helper;
        $this->deliveryServicesService = $deliveryServicesService;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param bool $requiredOnly
     * @return array
     */
    public function getDefaultOptions($order, $requiredOnly = false)
    {
        $options = $this->getOptions($this->getMethodKey($order));
        $options += $this->deliveryServicesService->getSelection($order, true);

        if (isset($options['PS'])) {
            $options['PS'] = $order->getData('dhlparcel_shipping_servicepoint_id');
        }

        $reference = $this->getReference($order);
        if ($reference) {
            $options['REFERENCE'] = $reference;
        }

        $reference2 = $this->getReference($order, 'reference2');
        if ($reference2) {
            $options['REFERENCE2'] = $reference2;
        }

        if ($this->helper->getConfigData('label/default_hide_shipper', $order->getStoreId())) {
            $options['SSN'] = '';
        }

        if ($this->helper->getConfigData('label/default_return_label', $order->getStoreId()) == ServiceOptionDefault::OPTION_SKIP_NOT_AVAILABLE
            || $this->helper->getConfigData('label/default_return_label', $order->getStoreId()) == ServiceOptionDefault::OPTION_IF_AVAILABLE
            && !$requiredOnly) {
            $options['ADD_RETURN_LABEL'] = '';
        }

        if ($this->helper->getConfigData('label/default_age_check', $order->getStoreId()) == ServiceOptionDefault::OPTION_SKIP_NOT_AVAILABLE
            || $this->helper->getConfigData('label/default_age_check', $order->getStoreId()) == ServiceOptionDefault::OPTION_IF_AVAILABLE
            && !$requiredOnly) {
            $options['AGE_CHECK'] = '';
        }

        if ($this->helper->getConfigData('label/default_extra_assured', $order->getStoreId()) == ServiceOptionDefault::OPTION_SKIP_NOT_AVAILABLE
            || $this->helper->getConfigData('label/default_extra_assured', $order->getStoreId()) == ServiceOptionDefault::OPTION_IF_AVAILABLE
            && !$requiredOnly) {
            $minimumOrderAmount = str_replace(',', '.', $this->helper->getConfigData('label/default_extra_assured_min'));
            if (!is_numeric($minimumOrderAmount) || $order->getSubtotal() >= floatval($minimumOrderAmount)) {
                $options['EA'] = '';
            }
        }

        return $options;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return string|string[]
     */
    public function getMethodKey($order)
    {
        return str_replace('dhlparcel_', '', $order->getShippingMethod());
    }

    public function getOptions($shippingMethodKey)
    {
        $collection = $this->getOptionsCollection();

        if (!array_key_exists($shippingMethodKey, $collection)) {
            $shippingMethodKey = self::SHIPPING_METHOD_DOOR;
        }

        return $collection[$shippingMethodKey];
    }

    public function searchMethodKey($options)
    {
        if (!is_array($options)) {
            return null;
        }

        $collection = $this->getOptionsCollection();
        $optionKeys = array_flip($options);

        foreach ($collection as $key => $presetOptions) {
            if (empty(array_diff_key($presetOptions, $optionKeys)) &&
                empty(array_diff_key($optionKeys, $presetOptions))) {
                return $key;
            }
        }
        return null;
    }

    protected function getOptionsCollection()
    {
        return [
            self::SHIPPING_METHOD_SAMEDAY              => ['DOOR' => '', 'SDD' => ''],
            self::SHIPPING_METHOD_MORNING              => ['DOOR' => '', 'EXP' => ''],
            self::SHIPPING_METHOD_EVENING              => ['DOOR' => '', 'EVE' => ''],
            self::SHIPPING_METHOD_NO_NEIGHBOUR         => ['DOOR' => '', 'NBB' => ''],
            self::SHIPPING_METHOD_NO_NEIGHBOUR_EVENING => ['DOOR' => '', 'EVE' => '', 'NBB' => ''],
            self::SHIPPING_METHOD_SATURDAY             => ['DOOR' => '', 'S' => ''],
            self::SHIPPING_METHOD_SERVICE_POINT        => ['PS' => ''],
            self::SHIPPING_METHOD_DOOR                 => ['DOOR' => '']
        ];
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function defaultToBusiness($storeId = null)
    {
        return boolval($this->helper->getConfigData('label/default_to_business', $storeId));
    }

    public function filterSkippableDefaults($presetOptions, $storeId)
    {
        $options = [];
        if (array_key_exists('EA', $presetOptions)) {
            if ($this->helper->getConfigData('label/default_extra_assured', $storeId) == ServiceOptionDefault::OPTION_IF_AVAILABLE) {
                $options[] = 'EA';
            }
        }

        if (array_key_exists('ADD_RETURN_LABEL', $presetOptions)) {
            if ($this->helper->getConfigData('label/default_return_label', $storeId) == ServiceOptionDefault::OPTION_IF_AVAILABLE) {
                $options[] = 'ADD_RETURN_LABEL';
            }
        }

        if (array_key_exists('AGE_CHECK', $presetOptions)) {
            if ($this->helper->getConfigData('label/default_age_check', $storeId) == ServiceOptionDefault::OPTION_IF_AVAILABLE) {
                $options[] = 'AGE_CHECK';
            }
        }

        return $options;
    }

    public function getTranslations()
    {
        return [
            'DOOR'             => __('At the door'),
            'BP'               => __('In the mailbox'),
            'SP'               => __('DHL ServicePoint delivery'),
            'REFERENCE'        => __('Reference'),
            'REFERENCE2'       => __('Reference 2'),
            'ADD_RETURN_LABEL' => __('Return label'),
            'EA'               => __('Extra Assured'),
            'HANDT'            => __('Signature on delivery'),
            'EVE'              => __('Evening delivery (6 AM to 9.30 PM)'),
            'NBB'              => __('No delivery to neighbour'),
            'INS'              => __('Shipment insurance'),
            'S'                => __('Saturday delivery (9 AM to 3 PM)'),
            'EXP'              => __('Expresser'),
            'BOUW'             => __('Delivery on construction site'),
            'EXW'              => __('Ex Works'),
            'SSN'              => __('Hide Shipper'),
            'SDD'              => __('DHL Same-day delivery (6 p.m. to 9.30 p.m.)'),
            'AGE_CHECK'        => __('Age check (18+)'),
        ];
    }

    public function getTranslation($key)
    {
        $translations = $this->getTranslations();
        if (!array_key_exists($key, $translations)) {
            return null;
        }
        return $translations[$key];
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $order
     * @param string $type
     * @return bool|mixed
     */
    protected function getReference($order, $type = 'reference')
    {
        if ($type === 'reference') {
            $enabled = $this->helper->getConfigData('label/default_reference_enabled', $order->getStoreId());
            $referenceOption = $this->helper->getConfigData('label/default_reference_source', $order->getStoreId());
            $customText = $this->helper->getConfigData('label/default_reference_text', $order->getStoreId());
        } else {
            $enabled = $this->helper->getConfigData('label/default_reference2_enabled', $order->getStoreId());
            $referenceOption = $this->helper->getConfigData('label/default_reference2_source', $order->getStoreId());
            $customText = $this->helper->getConfigData('label/default_reference2_text', $order->getStoreId());
        }

        if (!$enabled) {
            return false;
        }

        switch ($referenceOption) {
            case ReferenceOptions::OPTION_ORDER_NUMBER:
                $reference = $order->getRealOrderId();
                break;
            case ReferenceOptions::OPTION_ORDER_ID:
                $reference = $order->getId();
                break;
            case ReferenceOptions::OPTION_CUSTOM_TEXT:
                $reference = $customText;
                break;
            default:
                $reference = false;
        }
        return $reference;
    }
}
