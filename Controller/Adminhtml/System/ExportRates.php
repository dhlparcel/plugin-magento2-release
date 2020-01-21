<?php

namespace DHLParcel\Shipping\Controller\Adminhtml\System;

use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Store\Model\StoreManagerInterface;

class ExportRates extends \Magento\Config\Controller\Adminhtml\System\AbstractConfig
{
    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    protected $fileFactory;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * ExportRates constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Config\Model\Config\Structure $configStructure
     * @param \Magento\Config\Controller\Adminhtml\System\ConfigSectionChecker $sectionChecker
     * @param FileFactory $fileFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Config\Model\Config\Structure $configStructure,
        \Magento\Config\Controller\Adminhtml\System\ConfigSectionChecker $sectionChecker,
        FileFactory $fileFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->fileFactory = $fileFactory;
        parent::__construct($context, $configStructure, $sectionChecker);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        /** @var $gridBlock \DHLParcel\Shipping\Block\Adminhtml\VariableRate\ExportGrid */
        $gridBlock = $this->_view->getLayout()->createBlock(
            \DHLParcel\Shipping\Block\Adminhtml\VariableRate\ExportGrid::class
        );
        $storeId = $this->getRequest()->getParam('store');
        $website = $this->storeManager->getWebsite($this->getRequest()->getParam('website'));
        $store = $this->storeManager->getStore($storeId);

        if ($this->getRequest()->getParam('shippingMethod')) {
            $shippingMethod = $this->getRequest()->getParam('shippingMethod');
        } else {
            $result = $this->resultFactory
                ->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
                ->setData(["error" => "shippingMethod was not set"]);
            return $result;
        }
        if ($this->getRequest()->getParam('conditionName')) {
            $conditionName = $this->getRequest()->getParam('conditionName');
        } else {
            if ($storeId) {
                $conditionName = $store->getConfig('carriers/' . $shippingMethod . '/condition_name');
            } else {
                $conditionName = $website->getConfig('carriers/' . $shippingMethod . '/condition_name');
            }
        }

        $fileName = 'dhl_' . $shippingMethod . '_rates_' . date('Y-m-d_H-i-s') . '.csv';

        $gridBlock
            ->setWebsiteId($website->getId())
            ->setStoreId($storeId)
            ->setConditionName($conditionName)
            ->setMethodName($shippingMethod);
        $content = $gridBlock->getCsvFile();
        return $this->fileFactory->create($fileName, $content, \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
    }
}
