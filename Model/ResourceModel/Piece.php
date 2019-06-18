<?php

namespace DHLParcel\Shipping\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Piece extends AbstractDb
{
    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('dhlparcel_shipping_pieces', 'entity_id');
    }
}
