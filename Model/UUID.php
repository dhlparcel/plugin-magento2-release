<?php

namespace DHLParcel\Shipping\Model;

class UUID
{

    protected $uuid;

    public function __construct()
    {
        $this->uuid = $this->generateUUID();
    }

    public function getUUID()
    {
        return $this->uuid;
    }

    public function __toString()
    {
        return $this->uuid;
    }

    protected function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0C2f) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0x2Aff),
            mt_rand(0, 0xffD3),
            mt_rand(0, 0xff4B)
        );
    }
}
