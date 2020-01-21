<?php

namespace DHLParcel\Shipping\Block\Adminhtml\VariableRate;

use DHLParcel\Shipping\Model\Config\Source\RateConditions;

class ExportGrid extends \Magento\Backend\Block\Widget\Grid\Extended
{
    /**
     * @var int
     */
    protected $websiteId;
    /**
     * @var int
     */
    protected $storeId;
    /**
     * @var string
     */
    protected $conditionName;
    /**
     * @var string
     */
    protected $methodName;
    /**
     * @var \DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\CollectionFactory
     */
    protected $collectionFactory;

    /**
     * ExportGrid constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Backend\Helper\Data $backendHelper
     * @param \DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\CollectionFactory $collectionFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\CollectionFactory $collectionFactory,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * Define grid properties
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('shippingDHLRateGrid');
        $this->_exportPageSize = 10000;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getWebsiteId()
    {
        if ($this->websiteId === null) {
            $this->websiteId = $this->_storeManager->getWebsite()->getId();
        }
        return $this->websiteId;
    }

    /**
     * @param $websiteId
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setWebsiteId($websiteId)
    {
        $this->websiteId = $this->_storeManager->getWebsite($websiteId)->getId();
        return $this;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getStoreId()
    {
        if ($this->storeId === null) {
            $this->storeId = $this->_storeManager->getStore()->getId();
        }
        return $this->storeId;
    }

    /**
     * @param $storeId
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $this->_storeManager->getStore($storeId)->getId();
        return $this;
    }

    /**
     * @return string
     */
    public function getConditionName()
    {
        return $this->conditionName;
    }

    /**
     * @param $name
     * @return $this
     */
    public function setConditionName($name)
    {
        $this->conditionName = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }

    /**
     * @param string $methodName
     * @return Grid
     */
    public function setMethodName($methodName)
    {
        $this->methodName = $methodName;
        return $this;
    }

    /**
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareCollection()
    {
        /** @var $collection \DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\Collection */
        $collection = $this->collectionFactory->create();
        $collection
            ->setConditionFilter($this->getConditionName())
            ->setWebsiteFilter($this->getWebsiteId())
            ->setStoreFilter($this->getStoreId())
            ->setMethodFilter($this->getMethodName());
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'dest_country',
            ['header' => __('Country'), 'index' => 'dest_country', 'default' => '*']
        );

        $this->addColumn(
            'dest_region',
            ['header' => __('Region/State'), 'index' => 'dest_region', 'default' => '*']
        );

        $this->addColumn(
            'dest_zip',
            ['header' => __('Zip/Postal Code'), 'index' => 'dest_zip', 'default' => '*']
        );

        $label = RateConditions::getName($this->getConditionName(), false);
        $this->addColumn('condition_value', ['header' => $label, 'index' => 'condition_value']);

        $this->addColumn('price', ['header' => __('Shipping Price'), 'index' => 'price']);

        return parent::_prepareColumns();
    }
}
