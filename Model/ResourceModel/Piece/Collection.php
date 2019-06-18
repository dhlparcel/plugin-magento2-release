<?php

namespace DHLParcel\Shipping\Model\ResourceModel\Piece;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(
            'DHLParcel\Shipping\Model\Piece',
            'DHLParcel\Shipping\Model\ResourceModel\Piece'
        );
    }
}
