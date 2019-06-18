<?php

namespace DHLParcel\Shipping\Controller\Adminhtml\Log;

class View extends \Magento\Backend\App\Action
{
    protected $dir;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Filesystem\DirectoryList $dir
    ) {
        $this->dir = $dir;
        parent::__construct($context);
    }

    public function execute()
    {
        $logFile = $this->dir->getRoot() . \DHLParcel\Shipping\Logger\DebugLogger::LOG_LOCATION;
        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/html');

        if (file_exists($logFile)) {
            $output = file_get_contents($logFile);
            if (empty($output)) {
                $output = __('Log file is empty');
            }
        } else {
            $output = __('Log file not found');
        }
        return $result->setContents("<html><head><style>body{background-color: #222;color: #CCC}</style></head><body><pre>$output</pre></body></html>");
    }
}
