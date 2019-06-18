<?php

namespace DHLParcel\Shipping\Logger;

class NoticeHandler extends \Magento\Framework\Logger\Handler\Base
{
    protected $loggerType = DebugLogger::NOTICE;
    protected $fileName = DebugLogger::LOG_LOCATION;
}
