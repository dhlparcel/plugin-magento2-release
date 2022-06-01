<?php

namespace DHLParcel\Shipping\Logger;

use DHLParcel\Shipping\Helper\Data;

class DebugLogger extends \Monolog\Logger
{
    const LOG_LOCATION = '/var/log/dhlparcel_shipping.log';
    protected $helper;
    protected $active;

    /**
     * Logger constructor.
     * @param $name
     * @param Data $helper
     * @param array $handlers
     * @param array $processors
     */
    public function __construct($name, Data $helper, $handlers = [], $processors = [])
    {
        parent::__construct(strval($name), $handlers, $processors);

        $this->helper = $helper;
        $this->active = boolval($this->helper->getConfigData('debug/enabled'));

        if (!$this->active) {
            $this->pushHandler(new \Monolog\Handler\NullHandler());
        }
    }
}
