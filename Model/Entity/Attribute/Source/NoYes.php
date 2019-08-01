<?php

namespace DHLParcel\Shipping\Model\Entity\Attribute\Source;

class NoYes extends \Magento\Eav\Model\Entity\Attribute\Source\Boolean
{
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['label' => __('No'), 'value' => self::VALUE_NO],
                ['label' => __('Yes'), 'value' => self::VALUE_YES],
            ];
        }
        return $this->_options;
    }
}
