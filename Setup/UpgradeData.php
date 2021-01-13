<?php

namespace DHLParcel\Shipping\Setup;

use DHLParcel\Shipping\Model\Carrier;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    protected $configReader;
    protected $configWriter;
    /** @var EavSetup */
    protected $eavSetup;
    protected $eavSetupFactory;

    /**
     * UpgradeData constructor.
     * @param ScopeConfigInterface $configReader
     * @param WriterInterface $configWriter
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ScopeConfigInterface $configReader,
        WriterInterface $configWriter,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->configReader = $configReader;
        $this->configWriter = $configWriter;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $this->eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        if (version_compare($context->getVersion(), "1.0.1", "<")) {
            $configs = [
                'carriers/dhlparcel/label/default_extra_insurance' => 'carriers/dhlparcel/label/default_extra_assured'
            ];
            $this->updateConfigPaths($configs);
        }

        if (version_compare($context->getVersion(), "1.0.2", "<")) {
            $configs = [
                'carriers/dhlparcel/usability/bulk/print' => 'carriers/dhlparcel/usability/bulk/download'
            ];
            $this->updateConfigPaths($configs);
            $this->addProductBlacklistAttributes();
        }

        if (version_compare($context->getVersion(), "1.0.5", "<")) {
            $this->updateProductBlacklistServicePointAttributeSourceModel();
        }

        if (version_compare($context->getVersion(), "1.0.6", "<")) {
            $this->updateProductBlacklistAttributeLabels();
        }

        if (version_compare($context->getVersion(), "1.0.10", "<")) {
            $this->updateBlacklistSourceClass();
        }

        if (version_compare($context->getVersion(), "1.0.15", "<")) {
            $configs = [
                'carriers/dhlparcel/usability/auto_print/enabled'         => 'carriers/dhlparcel/usability/automation/shipment',
                'carriers/dhlparcel/usability/auto_print/on_order_status' => 'carriers/dhlparcel/usability/automation/on_order_status',
                'carriers/dhlparcel/usability/auto_print/auto_print'      => 'carriers/dhlparcel/usability/automation/print'
            ];
            $this->updateConfigPaths($configs);
        }

        if (version_compare($context->getVersion(), "1.0.18", "<")) {
            $this->addBlacklistAll();
        }

        $setup->endSetup();
    }

    /**
     * @param $replaceConfigs
     */
    private function updateConfigPaths($replaceConfigs)
    {
        foreach ($replaceConfigs as $oldPath => $newPath) {
            if ($this->configReader->getValue($oldPath)) {
                // Replace values to new path
                $this->configWriter->save($newPath, $this->configReader->getValue($oldPath));
            }
            $this->configWriter->delete($oldPath);
        }
    }

    private function addAttributesToAttributeSets($attributeCodes = [])
    {
        $entityTypeId = $this->eavSetup->getEntityTypeId('catalog_product');
        $attributeSetIds = $this->eavSetup->getAllAttributeSetIds($entityTypeId);
        $groupName = 'DHL Parcel';

        $attributeIds = [];
        foreach ($attributeCodes as $attributeCode) {
            $attributeIds[] = $this->eavSetup->getAttributeId($entityTypeId, $attributeCode);
        }
        foreach ($attributeSetIds as $attributeSetId) {
            $this->eavSetup->addAttributeGroup($entityTypeId, $attributeSetId, $groupName, 50);
            $attributeGroupId = $this->eavSetup->getAttributeGroupId($entityTypeId, $attributeSetId, $groupName);
            // Add existing attribute to group

            foreach ($attributeIds as $attributeId) {
                $this->eavSetup->addAttributeToGroup($entityTypeId, $attributeSetId, $attributeGroupId, $attributeId, null);
            }
        }
    }

    private function addProductBlacklistAttributes()
    {
        $this->eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_SERVICEPOINT,
            [
                'type'                    => 'int',
                'backend'                 => '',
                'frontend'                => '',
                'label'                   => 'Disable in checkout: delivery methods with ServicePoint service option',
                'input'                   => 'select',
                'class'                   => '',
                'source'                  => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'global'                  => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'                 => true,
                'required'                => false,
                'user_defined'            => false,
                'default'                 => 0,
                'searchable'              => false,
                'filterable'              => false,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => false,
                'unique'                  => false,
                'apply_to'                => ''
            ]
        );
        $this->eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_GENERAL,
            [
                'type'                    => 'varchar',
                'backend'                 => \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
                'frontend'                => '',
                'label'                   => 'Disable in checkout: delivery methods with these service options',
                'input'                   => 'multiselect',
                'class'                   => '',
                'source'                  => \DHLParcel\Shipping\Model\Entity\Attribute\Source\Blacklist::class,
                'global'                  => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'                 => true,
                'required'                => false,
                'user_defined'            => false,
                'default'                 => '',
                'searchable'              => false,
                'filterable'              => false,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => false,
                'unique'                  => false,
                'apply_to'                => ''
            ]
        );
        $this->addAttributesToAttributeSets([Carrier::BLACKLIST_SERVICEPOINT, Carrier::BLACKLIST_GENERAL]);
    }

    private function updateProductBlacklistServicePointAttributeSourceModel()
    {
        $this->eavSetup->updateAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_SERVICEPOINT,
            'source_model',
            \DHLParcel\Shipping\Model\Entity\Attribute\Source\NoYes::class
        );
    }

    private function updateProductBlacklistAttributeLabels()
    {
        $this->eavSetup->updateAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_SERVICEPOINT,
            'frontend_label',
            'Disable in checkout: delivery methods with ServicePoint service option'
        );

        $this->eavSetup->updateAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_GENERAL,
            'frontend_label',
            'Disable in checkout: delivery methods with these service options'
        );
    }

    private function updateBlacklistSourceClass()
    {
        $this->eavSetup->updateAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_GENERAL,
            'source_model',
            \DHLParcel\Shipping\Model\Entity\Attribute\Source\Blacklist::class
        );
    }

    private function addBlacklistAll()
    {
        $this->eavSetup->updateAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_SERVICEPOINT,
            'position',
            200
        );

        $this->eavSetup->updateAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_GENERAL,
            'position',
            300
        );

        $this->eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            Carrier::BLACKLIST_ALL,
            [
                'type'                    => 'int',
                'backend'                 => '',
                'frontend'                => '',
                'label'                   => 'Disable all DHL delivery methods in checkout',
                'input'                   => 'select',
                'class'                   => '',
                'source'                  => \DHLParcel\Shipping\Model\Entity\Attribute\Source\NoYes::class,
                'global'                  => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'                 => true,
                'required'                => false,
                'user_defined'            => false,
                'default'                 => 0,
                'searchable'              => false,
                'filterable'              => false,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => false,
                'unique'                  => false,
                'apply_to'                => '',
                'sort_order'              => 100,
            ]
        );

        $this->addAttributesToAttributeSets([Carrier::BLACKLIST_ALL]);
    }
}
