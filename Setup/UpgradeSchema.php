<?php

namespace DHLParcel\Shipping\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
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
        if (version_compare($context->getVersion(), "1.0.11", "<")) {
            $this->installDeliveryServices($setup);
        }
        if (version_compare($context->getVersion(), "1.0.12", "<")) {
            $this->installServiceOptionsStorage($setup);
            $this->dropServicePointCountry($setup);
            $this->updateServicePointId($setup);
            $this->updateDeliveryTimeSelection($setup);
            $this->updateDeliveryServices($setup);
        }
        $installer->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function updateDeliveryServices(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->modifyColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_deliveryservices_selection',
            [
                'type'     => Table::TYPE_TEXT,
                'length'   => 255,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Services Selection',
            ]
        );

        $setup->getConnection()->modifyColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_deliveryservices_selection',
            [
                'type'     => Table::TYPE_TEXT,
                'length'   => 255,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Services Selection',
            ]
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function updateDeliveryTimeSelection(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->modifyColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_deliverytimes_selection',
            [
                'type'     => Table::TYPE_BLOB,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Selection',
            ]
        );

        // Same fields for Quote & SalesOrder
        $setup->getConnection()->modifyColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_deliverytimes_selection',
            [
                'type'     => Table::TYPE_BLOB,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Selection',
            ]
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function updateServicePointId(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->modifyColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_servicepoint_id',
            [
                'type'     => Table::TYPE_TEXT,
                'length'   => 32,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping ServicePoint ID',
            ]
        );

        $setup->getConnection()->modifyColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_servicepoint_id',
            [
                'type'     => Table::TYPE_TEXT,
                'length'   => 32,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping ServicePoint ID',
            ]
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function dropServicePointCountry(SchemaSetupInterface $setup)
    {
        /** Remove unnecessary ServicePoint country */
        $setup->getConnection()->dropColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_servicepoint_country'
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function installServiceOptionsStorage(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('dhlparcel_shipping_pieces'),
            'service_options',
            [
                'type'     => Table::TYPE_TEXT,
                'length'   => 255,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Service Options Selection'
            ]
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function installDeliveryTimes(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_deliverytimes_selection',
            [
                'type'     => Table::TYPE_TEXT,
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

        // Same fields for Quote & SalesOrder
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

        /** Update grid */
        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order_grid'),
            'dhlparcel_shipping_deliverytimes_priority',
            [
                'type'     => Table::TYPE_BIGINT,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Priority',
            ]
        );

        /** Remove unnecessary ServicePoint country */
        $setup->getConnection()->dropColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_servicepoint_country'
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function addServicePointToSalesOrderGrid(SchemaSetupInterface $setup)
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

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function installSaveShipmentRequests(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('dhlparcel_shipping_pieces'),
            'shipment_request',
            [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'comment'  => 'Shipment Request'
            ]
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function updateVariableRateTable(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('dhlparcel_shipping_rates'),
            'store_id',
            [
                'type'     => Table::TYPE_INTEGER,
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
                AdapterInterface::INDEX_TYPE_UNIQUE
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
                AdapterInterface::INDEX_TYPE_UNIQUE
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
            AdapterInterface::INDEX_TYPE_UNIQUE
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function installDeliveryServices(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_deliveryservices_selection',
            [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Services Selection',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_deliveryservices_selection',
            [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Services Selection',
            ]
        );
    }
}
