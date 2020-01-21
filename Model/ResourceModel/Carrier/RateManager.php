<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace DHLParcel\Shipping\Model\ResourceModel\Carrier;

use DHLParcel\Shipping\Model\Config\Source\RateConditions;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\Import;
use DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\RateQuery;
use DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\RateQueryFactory;

class RateManager extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * @var int
     */
    protected $_importedRows = 0;
    /**
     * @var array|string[]
     */
    protected $conditionFullNames = [];
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $coreConfig;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var Filesystem
     */
    protected $filesystem;
    /**
     * @var Import
     */
    private $import;
    /**
     * @var RateQueryFactory
     */
    private $rateQueryFactory;
    private $request;

    /**
     * RateManager constructor.
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $coreConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     * @param Import $import
     * @param RateQueryFactory $rateQueryFactory
     * @param null $connectionName
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\App\Config\ScopeConfigInterface $coreConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        Import $import,
        RateQueryFactory $rateQueryFactory,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->coreConfig = $coreConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
        $this->import = $import;
        $this->rateQueryFactory = $rateQueryFactory;
        $this->request = $request;
    }

    /**
     * Define main table and id field name
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('dhlparcel_shipping_rates', 'pk');
    }

    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @param $method
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getRate(\Magento\Quote\Model\Quote\Address\RateRequest $request, $method)
    {
        $connection = $this->getConnection();

        $websiteId = 0;
        $storeId = 0;

        $select = $connection->select()->from($this->getMainTable());
        /** @var RateQuery $preQuery */
        $preQuery = $this->rateQueryFactory->create(['request' => $request]);
        $preQuery->preparePreSelect($select);
        $bindings = $preQuery->getPreBindings($method, 0, (int)$request->getStoreId());
        if ($connection->fetchRow($select, $bindings)) {
            $storeId = (int)$request->getStoreId();
        } else {
            $select = $connection->select()->from($this->getMainTable());
            /** @var RateQuery $preQuery */
            $preQuery = $this->rateQueryFactory->create(['request' => $request]);
            $preQuery->preparePreSelect($select);
            $bindings = $preQuery->getPreBindings($method, (int)$request->getWebsiteId(), 0);
            if ($connection->fetchRow($select, $bindings)) {
                $websiteId = (int)$request->getWebsiteId();
            }
        }

        $select = $connection->select()->from($this->getMainTable());
        /** @var RateQuery $rateQuery */
        $rateQuery = $this->rateQueryFactory->create(['request' => $request]);
        $rateQuery->prepareSelect($select);

        $bindings = $rateQuery->getBindings($method, $websiteId, $storeId);

        $result = $connection->fetchRow($select, $bindings);
        // Normalize destination zip code
        if ($result && $result['dest_zip'] == '*') {
            $result['dest_zip'] = '';
        }

        return $result;
    }

    /**
     * @param array $condition
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function deleteByCondition(array $condition)
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();
        $connection->delete($this->getMainTable(), $condition);
        $connection->commit();
        return $this;
    }

    /**
     * @param array $fields
     * @param array $values
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return void
     */
    private function importData(array $fields, array $values)
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            if (count($fields) && count($values)) {
                $this->getConnection()->insertArray($this->getMainTable(), $fields, $values);
                $this->_importedRows += count($values);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $connection->rollBack();
            throw new \Magento\Framework\Exception\LocalizedException(__('Unable to import data'), $e);
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->critical($e);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something went wrong while importing table rates.')
            );
        }
        $connection->commit();
    }

    /**
     * @param \Magento\Framework\DataObject $object
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function uploadAndImport(\Magento\Framework\DataObject $object)
    {
        $method = $object->getData('group_id');
        $basePath = str_replace($object->getData('field'), 'rate_condition', $object->getData('path'));
        $files = $this->request->getFiles()->get('groups');

        /**
         * @var \Magento\Framework\App\Config\Value $object
         */
        if (empty($files['dhlparcel']['groups']['shipping_methods']['groups'][$method]['fields']['import']['value']['tmp_name'])) {
            return $this;
        }
        $filePath = $files['dhlparcel']['groups']['shipping_methods']['groups'][$method]['fields']['import']['value']['tmp_name'];

        $storeId = 0;
        $websiteId = 0;
        switch ($object->getScope()) {
            case 'websites':
                $websiteId = $this->storeManager->getWebsite($object->getScopeId())->getId();
                break;
            case 'stores':
                $storeId = $this->storeManager->getStore($object->getScopeId())->getId();
                break;
        }
        $conditionName = $this->getConditionName($object, $method, $basePath);

        $file = $this->getCsvFile($filePath);
        try {
            // delete old data by website and condition name
            $condition = [
                'website_id = ?'     => $websiteId,
                'store_id = ?'       => $storeId,
                'condition_name = ?' => $conditionName,
                'method_name = ?'    => $method
            ];
            $this->deleteByCondition($condition);

            $columns = $this->import->getColumns();
            $conditionFullName = $this->getConditionFullName($conditionName);
            foreach ($this->import->getData($file, $websiteId, $storeId, $conditionName, $conditionFullName, $method) as $bunch) {
                $this->importData($columns, $bunch);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something went wrong while importing dhl shipping rates.')
            );
        } finally {
            $file->close();
        }

        if ($this->import->hasErrors()) {
            $error = __(
                'We couldn\'t import this file because of these errors: %1',
                implode(" \n", $this->import->getErrors())
            );
            throw new \Magento\Framework\Exception\LocalizedException($error);
        }
    }

    /**
     * @param \Magento\Framework\DataObject $object
     * @param $method
     * @param $path
     * @return mixed|string
     */
    public function getConditionName(\Magento\Framework\DataObject $object, $method, $path)
    {
        if ($object->getData('groups/dhlparcel/groups/shipping_methods/groups/' . $method . '/fields/rate_condition/inherit') == '1') {
            $conditionName = (string)$this->coreConfig->getValue($path, 'default');
        } else {
            $conditionName = $object->getData('groups/dhlparcel/groups/shipping_methods/groups/' . $method . '/fields/rate_condition/value');
        }
        return $conditionName;
    }

    /**
     * @param string $filePath
     * @return \Magento\Framework\Filesystem\File\ReadInterface
     */
    private function getCsvFile($filePath)
    {
        $tmpDirectory = $this->filesystem->getDirectoryRead(DirectoryList::SYS_TMP);
        $path = $tmpDirectory->getRelativePath($filePath);
        return $tmpDirectory->openFile($path);
    }

    /**
     * @param $conditionName
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getConditionFullName($conditionName)
    {
        if (!isset($this->conditionFullNames[$conditionName])) {
            $this->conditionFullNames[$conditionName] = RateConditions::getName($conditionName, false);
        }

        return $this->conditionFullNames[$conditionName];
    }
}
