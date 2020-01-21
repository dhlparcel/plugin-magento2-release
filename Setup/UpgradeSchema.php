<?php

namespace DHLParcel\Shipping\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $installer = $setup;

        $installer->startSetup();
        if (version_compare($context->getVersion(), "1.0.2", "<")) {
            $this->installDeliveryTimes($setup);
        }
        if (version_compare($context->getVersion(), "1.0.6", "<")) {
            $this->addServicePointToSalesOrderGrid($setup);
        }
        if (version_compare($context->getVersion(), "1.0.9", "<")) {
            $this->installSaveShipmentRequests($setup);
            $this->updateVariableRateTable($setup);
        }
        $installer->endSetup();
    }

    protected function installDeliveryTimes(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_deliverytimes_selection',
            [
                'type'     => Table::TYPE_BLOB,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Selection',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_deliverytimes_priority',
            [
                'type'     => Table::TYPE_BIGINT,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Priority',
            ]
        );

        // Same fields for Quote & SsalesOrder
        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_deliverytimes_selection',
            [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Selection',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_deliverytimes_priority',
            [
                'type'     => Table::TYPE_BIGINT,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Priority',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order_grid'),
            'dhlparcel_shipping_deliverytimes_priority',
            [
                'type'     => Table::TYPE_BIGINT,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Priority',
            ]
        );

        $setup->getConnection()->dropColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_servicepoint_country'
        );
    }

    public function addServicePointToSalesOrderGrid(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order_grid'),
            'dhlparcel_shipping_servicepoint_id',
            [
                'type'     => Table::TYPE_TEXT,
                'length'   => 32,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping ServicePoint ID',
            ]
        );
    }

    public function installSaveShipmentRequests(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('dhlparcel_shipping_pieces'),
            'shipment_request',
            [
                'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => true,
                'comment'  => 'Shipment Request'
            ]
        );
    }

    protected function updateVariableRateTable(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('dhlparcel_shipping_rates'),
            'store_id',
            [
                'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                'nullable' => false,
                'default'  => '0',
                'comment'  => 'Store id'
            ]
        );
        $setup->getConnection()->dropIndex(
            $setup->getTable('dhlparcel_shipping_rates'),
            $setup->getIdxName(
                'shipping_tablerate',
                [
                    'website_id',
                    'method_name',
                    'dest_country_id',
                    'dest_region_id',
                    'dest_zip',
                    'condition_name',
                    'condition_value',
                ],
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
            )
        );
        $setup->getConnection()->addIndex(
            $setup->getTable('dhlparcel_shipping_rates'),
            $setup->getIdxName(
                'dhlparcel_shipping_rates',
                [
                    'website_id',
                    'store_id',
                    'method_name',
                    'dest_country_id',
                    'dest_region_id',
                    'dest_zip',
                    'condition_name',
                    'condition_value',
                ],
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            [
                'website_id',
                'store_id',
                'method_name',
                'dest_country_id',
                'dest_region_id',
                'dest_zip',
                'condition_name',
                'condition_value',
            ],
            \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
        );
    }
}
