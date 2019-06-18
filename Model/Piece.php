<?php

namespace DHLParcel\Shipping\Model;

use Magento\Framework\Model\AbstractModel;

class Piece extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('DHLParcel\Shipping\Model\ResourceModel\Piece');
    }
}
