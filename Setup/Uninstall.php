<?php

namespace DHLParcel\Shipping\Setup;

use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class Uninstall implements UninstallInterface
{
    var $configReader;
    var $configWriter;

    public function __construct(
        ScopeConfigInterface $configReader,
        WriterInterface $configWriter
    ) {
        $this->configReader = $configReader;
        $this->configWriter = $configWriter;
    }
    /**
     * {@inheritdoc}
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $connection = $setup->getConnection();
        $connection->dropTable($connection->getTableName('dhlparcel_shipping_pieces'));
        $connection->dropTable($connection->getTableName('dhlparcel_shipping_rates'));
        $setup->endSetup();
    }
}
