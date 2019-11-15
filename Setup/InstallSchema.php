<?php
/**
 * Dhl Shipping
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to
 * newer versions in the future.
 *
 * PHP version 5.6+
 *
 * @category  DHLParcel
 * @package   DHLParcel\Shipping
 * @author    Ron Oerlemans <ron.oerlemans@dhl.com>
 * @copyright 2017 DHLParcel
 * @link      https://www.dhlparcel.nl/
 */

namespace DHLParcel\Shipping\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $this->installTrack($setup);
        $this->installRates($setup);
        $this->installServicePoint($setup);
        $setup->endSetup();
    }

    protected function installTrack(SchemaSetupInterface $setup)
    {
        $table = $setup->getConnection()->newTable(
            $setup->getTable('dhlparcel_shipping_pieces')

        )->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
            null,
            [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary'  => true,
            ],
            'Entity Id'

        )->addColumn(
            'label_id',
            Table::TYPE_TEXT,
            60,
            [
                'nullable' => false,
            ],
            'Label Id'

        )->addColumn(
            'tracker_code',
            Table::TYPE_TEXT,
            32,
            [
                'nullable' => false,
            ],
            'Tracker Code'

        )->addColumn(
            'postal_code',
            Table::TYPE_TEXT,
            255,
            [
                'nullable' => false,
            ],
            'Postal Code'

        )->addColumn(
            'parcel_type',
            Table::TYPE_TEXT,
            32,
            [
                'nullable' => false,
            ],
            'Parcel Type'

        )->addColumn(
            'piece_number',
            Table::TYPE_TEXT,
            32,
            [
                'nullable' => false,
            ],
            'Piece Number'

        )->addColumn(
            'label_type',
            Table::TYPE_TEXT,
            32,
            [
                'nullable' => false,
            ],
            'Label Type'

        )->addColumn(
            'is_return',
            Table::TYPE_BOOLEAN,
            null,
            [
                'nullable' => false,
            ],
            'Is Return'

        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            [
                'nullable' => false,
                'default'  => Table::TIMESTAMP_INIT,
            ],
            'Created At'

        )->addColumn(
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            [
                'nullable' => false,
                'default'  => Table::TIMESTAMP_INIT_UPDATE,
            ],
            'Updated At'

        )->addIndex(
            $setup->getIdxName('sales_shipment_track', ['label_id']),
            ['label_id']

        )->setComment(
            'DHL Parcel Shipping Pieces'
        );

        $setup->getConnection()->createTable($table);
    }

    protected function installRates(SchemaSetupInterface $setup)
    {
        $table = $setup->getConnection()->newTable(
            $setup->getTable('dhlparcel_shipping_rates')

        )->addColumn(
            'pk',
            Table::TYPE_INTEGER,
            null,
            [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary'  => true,
            ],
            'Primary key'

        )->addColumn(
            'website_id',
            Table::TYPE_INTEGER,
            null,
            [
                'nullable' => false,
                'default'  => '0',
            ],
            'Website Id'

        )->addColumn(
            'method_name',
            Table::TYPE_TEXT,
            40,
            [
                'nullable' => false,
            ],
            'Shipping method name'

        )->addColumn(
            'dest_country_id',
            Table::TYPE_TEXT,
            4,
            [
                'nullable' => false,
                'default'  => '0',
            ],
            'Destination coutry ISO/2 or ISO/3 code'

        )->addColumn(
            'dest_region_id',
            Table::TYPE_INTEGER,
            null,
            [
                'nullable' => false,
                'default'  => '0',
            ],
            'Destination Region Id'

        )->addColumn(
            'dest_zip',
            Table::TYPE_TEXT,
            10,
            [
                'nullable' => false,
                'default'  => '*',
            ],
            'Destination Post Code (Zip)'

        )->addColumn(
            'condition_name',
            Table::TYPE_TEXT,
            20,
            [
                'nullable' => false,
            ],
            'Rate Condition name'
        )->addColumn(
            'condition_value',
            Table::TYPE_DECIMAL,
            '12,4',
            [
                'nullable' => false,
                'default'  => '0.0000',
            ],
            'Rate condition value'
        )->addColumn(
            'price',
            Table::TYPE_DECIMAL,
            '12,4',
            [
                'nullable' => false,
                'default'  => '0.0000',
            ],
            'Price'
        )->addColumn(
            'cost',
            Table::TYPE_DECIMAL,
            '12,4',
            [
                'nullable' => false,
                'default'  => '0.0000',
            ],
            'Cost'

        )->addIndex(
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
            ),
            [
                'website_id',
                'method_name',
                'dest_country_id',
                'dest_region_id',
                'dest_zip',
                'condition_name',
                'condition_value',
            ],
            [
                'type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
            ]
        )->setComment(
            'DHL Parcel Shipping Rates'
        );

        $setup->getConnection()->createTable($table);
    }

    protected function installServicePoint(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_servicepoint_id',
            [
                'type'     => 'text',
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping ServicePoint ID',
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'dhlparcel_shipping_servicepoint_country',
            [
                'type'     => Table::TYPE_TEXT,
                'length'   => 32,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping ServicePoint Country',
            ]
        );

        $setup->getConnection()->addColumn(
        // Same fields for quote & sales_order
            $setup->getTable('sales_order'),
            'dhlparcel_shipping_servicepoint_id',
            [
                'type'     => Table::TYPE_TEXT,
                'length'   => 32,
                'nullable' => true,
                'comment'  => 'DHL Parcel Shipping ServicePoint ID',
            ]
        );
    }

}
