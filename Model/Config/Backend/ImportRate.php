<?php

namespace DHLParcel\Shipping\Model\Config\Backend;

use \DHLParcel\Shipping\Model\ResourceModel\Carrier\RateManagerFactory;

class ImportRate extends \Magento\Framework\App\Config\Value
{
    /**
     * @var RateManagerFactory
     */
    protected $rateManagerFactory;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        RateManagerFactory $rateManagerFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->rateManagerFactory = $rateManagerFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return \Magento\Framework\App\Config\Value
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterSave()
    {
        if ($this->getData('field') !== 'import') {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid system.xml shipping method not found in ID'));
        }
        /** @var \DHLParcel\Shipping\Model\ResourceModel\Carrier\RateManager $rateManager */
        $rateManager = $this->rateManagerFactory->create();
        $rateManager->uploadAndImport($this);

        return parent::afterSave();
    }
}
