<?php

namespace DHLParcel\Shipping\Model\Config\Source;

use Magento\Framework\Exception\LocalizedException;

class RateConditions implements \Magento\Framework\Option\ArrayInterface
{
    const PACKAGE_VALUE = 'package_value';
    const PACKAGE_WEIGHT = 'package_weight';
    const PACKAGE_QUANTITY = 'package_qty';

    public static function getConditions()
    {
        return [
            self::PACKAGE_VALUE,
            self::PACKAGE_WEIGHT,
            self::PACKAGE_QUANTITY
        ];
    }

    /**
     * @param string $code
     * @param bool $short
     * @return \Magento\Framework\Phrase
     * @throws LocalizedException
     */
    public static function getName($code, $short = true)
    {
        switch ($code) {
            case self::PACKAGE_VALUE:
                return $short ? __('Price vs. Destination') : __('Order Subtotal (and above)');
                break;
            case self::PACKAGE_WEIGHT:
                return $short ? __('Weight vs. Destination') : __('Order weight (and above)');
                break;
            case self::PACKAGE_QUANTITY:
                return $short ? __('Item count vs. Destination') : __('Order items (and above)');
                break;
            default:
                throw new LocalizedException(__('Invalid condition code: "%1"', $code));
                break;
        }
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    public function toOptionArray()
    {
        $arr = [];
        foreach (self::getConditions() as $condition) {
            $arr[] = ['value' => $condition, 'label' => self::getName($condition)];
        }
        return $arr;
    }
}
