<?php

namespace DHLParcel\Shipping\Controller\Adminhtml\Log;

class Debug extends \Magento\Backend\App\Action
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
        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/html');
        $logFile = $this->_request->getParam('log');
        if ($logFile === null) {
            $fileList = [];
            foreach (scandir($this->dir->getRoot() . '/var/log/') as $file) {
                if ($file !== '.' && $file !== '..') {
                    $url = $this->getUrl('dhlparcel_shipping/log/debug', ['log' => $file]);
                    $fileList[] = '<li><a href="' . $url . '" target="_blank">' . $file . '</a></li>';
                }
            }
            $output = implode('', $fileList);
            
            $output .= "<h3>Vendors:</h3>";
            
            foreach (scandir($this->dir->getRoot() . '/vendor/') as $file) {
                if ($file !== '.' && $file !== '..') {
                    $fileList[] = '<li>' . $file . '</li>';
                }
            }
            
            
            $output .= implode('', $fileList);
            
            return $result->setContents("<html><head><style>body{background-color: #222;color: #CCC} a{color: #CCC}</style></head><body><ul>$output</ul></body></html>");
        } else {
            $logFile = $this->dir->getRoot() . '/var/log/' . $logFile;

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
}
