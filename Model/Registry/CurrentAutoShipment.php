<?php

namespace DHLParcel\Shipping\Model\Registry;

class CurrentAutoShipment
{
    protected $orderId;

    public function setOrderId($id)
    {
        $this->orderId = $id;
    }

    public function getOrderId()
    {
        return isset($this->orderId) ? $this->orderId : null;
    }
}
