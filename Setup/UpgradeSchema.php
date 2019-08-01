<?php

namespace DHLParcel\Shipping\Setup;

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
        $installer->endSetup();
    }

    protected function installDeliveryTimes(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_deliverytimes_selection',
            [
                'type'     => 'text',
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Selection',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_deliverytimes_priority',
            [
                'type'     => 'bigint',
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Priority',
            ]
        );

        // Same fields for Quote & SsalesOrder
        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_deliverytimes_selection',
            [
                'type'     => 'text',
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Selection',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_deliverytimes_priority',
            [
                'type'     => 'bigint',
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping Delivery Times Priority',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order_grid'),
            'dhlparcel_shipping_deliverytimes_priority',
            [
                'type'     => 'bigint',
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
                'type'     => 'text',
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping ServicePoint ID',
            ]
        );
    }
}
