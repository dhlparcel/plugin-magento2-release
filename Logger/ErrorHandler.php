<?php

namespace DHLParcel\Shipping\Logger;

class ErrorHandler extends \Magento\Framework\Logger\Handler\Base
{
    protected $loggerType = DebugLogger::ERROR;
    protected $fileName = DebugLogger::LOG_LOCATION;
}
