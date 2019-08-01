<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Config\Source\ReferenceOptions;
use DHLParcel\Shipping\Model\Config\Source\ServiceOptionDefault;

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

    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $order
     * @return array
     */
    public function getDefaultOptions($order, $requiredOnly = false)
    {
        $options = $this->getOptions(str_replace('dhlparcel_', '', $order->getShippingMethod()));

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

        if ($this->helper->getConfigData('label/default_hide_shipper')) {
            $options['SSN'] = '';
        }

        if ($this->helper->getConfigData('label/default_return_label') == ServiceOptionDefault::OPTION_SKIP_NOT_AVAILABLE
            || $this->helper->getConfigData('label/default_return_label') == ServiceOptionDefault::OPTION_IF_AVAILABLE
            && !$requiredOnly) {
            $options['ADD_RETURN_LABEL'] = '';
        }

        if ($this->helper->getConfigData('label/default_extra_assured') == ServiceOptionDefault::OPTION_SKIP_NOT_AVAILABLE
            || $this->helper->getConfigData('label/default_extra_assured') == ServiceOptionDefault::OPTION_IF_AVAILABLE
            && !$requiredOnly) {
            $options['EA'] = '';
        }

        return $options;
    }

    public function getOptions($shippingMethodKey)
    {
        switch ($shippingMethodKey) {
            case self::SHIPPING_METHOD_SAMEDAY:
                $options = ['DOOR' => '', 'SDD' => ''];
                break;
            case self::SHIPPING_METHOD_MORNING:
                $options = ['DOOR' => '', 'EXP' => ''];
                break;
            case self::SHIPPING_METHOD_EVENING:
                $options = ['DOOR' => '', 'EVE' => ''];
                break;
            case self::SHIPPING_METHOD_NO_NEIGHBOUR:
                $options = ['DOOR' => '', 'NBB' => ''];
                break;
            case self::SHIPPING_METHOD_NO_NEIGHBOUR_EVENING:
                $options = ['DOOR' => '', 'EVE' => '', 'NBB' => ''];
                break;
            case self::SHIPPING_METHOD_SATURDAY:
                $options = ['DOOR' => '', 'S' => ''];
                break;
            case self::SHIPPING_METHOD_SERVICE_POINT:
                $options = ['PS' => ''];
                break;
            case self::SHIPPING_METHOD_DOOR:
            default:
                $options = ['DOOR' => ''];
                break;
        }
        return $options;
    }

    public function defaultToBusiness()
    {
        return boolval($this->helper->getConfigData('label/default_to_business'));
    }

    public function filterSkippableDefaults($presetOptions)
    {
        $options = [];
        if (array_key_exists('EA', $presetOptions)) {
            if ($this->helper->getConfigData('label/default_extra_assured') == ServiceOptionDefault::OPTION_IF_AVAILABLE) {
                $options[] = 'EA';
            }
        }

        if (array_key_exists('ADD_RETURN_LABEL', $presetOptions)) {
            if ($this->helper->getConfigData('label/default_return_label') == ServiceOptionDefault::OPTION_IF_AVAILABLE) {
                $options[] = 'ADD_RETURN_LABEL';
            }
        }

        if (array_key_exists('AGE_CHECK', $presetOptions)) {
            if ($this->helper->getConfigData('label/default_age_check') == ServiceOptionDefault::OPTION_IF_AVAILABLE) {
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
            'EVE'              => __('Evening delivery (6 AM to 9 PM)'),
            'NBB'              => __('No delivery to neighbour'),
            'INS'              => __('Shipment insurance'),
            'S'                => __('Saturday delivery (9 AM to 3 PM)'),
            'EXP'              => __('Expresser'),
            'COD_CASH'         => __('Cash on delivery'),
            'BOUW'             => __('Delivery on construction site'),
            'EXW'              => __('Ex Works'),
            'SSN'              => __('Hide Shipper'),
            'SDD'              => __('DHL Same-day delivery (6 p.m. to 9 p.m.)'),
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
            $enabled = $this->helper->getConfigData('label/default_reference_enabled');
            $referenceOption = $this->helper->getConfigData('label/default_reference_source');
            $customText = $this->helper->getConfigData('label/default_reference_text');
        } else {
            $enabled = $this->helper->getConfigData('label/default_reference2_enabled');
            $referenceOption = $this->helper->getConfigData('label/default_reference2_source');
            $customText = $this->helper->getConfigData('label/default_reference2_text');
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
