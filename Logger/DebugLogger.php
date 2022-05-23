<?php

namespace DHLParcel\Shipping\Logger;

use DHLParcel\Shipping\Helper\Data;

class DebugLogger extends \Monolog\Logger
{
    protected $helper;
    protected $active;
    const LOG_LOCATION = '/var/log/dhlparcel_shipping.log';

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
    }

    public function addRecord(int $level, string $message, array $context = []): bool
    {
        if (!$this->active) {
            return false;
        }
        return parent::addRecord($level, $message, $context);
    }
}
