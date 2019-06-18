<?php

namespace DHLParcel\Shipping\Logger;

/**
 * Class InfoHandler
 * @package DHLParcel\Shipping\Logger
 *
 * Currently only Debug,Info,Notice,Warning methods are implemented, to add other logging functions handlers must be added for the required levels
 *
 */
class InfoHandler extends \Magento\Framework\Logger\Handler\Base
{
    protected $loggerType = DebugLogger::INFO;
    protected $fileName = DebugLogger::LOG_LOCATION;
}
