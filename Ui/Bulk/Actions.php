<?php

namespace DHLParcel\Shipping\Ui\Bulk;

use DHLParcel\Shipping\Helper\Data;
use Magento\Framework\UrlInterface;

class Actions extends \Magento\Ui\DataProvider\AbstractDataProvider implements \Zend\Stdlib\JsonSerializable
{

    protected $data;
    /* @var UrlInterface */
    protected $urlBuilder;
    protected $urlPath;
    protected $additionalData = [];
    /* @var Data */
    protected $helper;

    protected $skipActions = [];

    public function __construct(
        UrlInterface $urlBuilder,
        Data $helper,
        array $data = []
    ) {
        $this->data = $data;
        $this->urlBuilder = $urlBuilder;
        $this->helper = $helper;
        $this->prepareData();
    }
    
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $options = [];

        $skipCreate = in_array('create', $this->skipActions);
        $enabled = $this->helper->getConfigData('usability/bulk/create');
        if ($enabled && !$skipCreate) {
            $this->addOptionStack(
                $options,
                'create',
                __('Create labels (excluding mailbox and envelope)'),
                $this->urlPath . 'create'
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/create_mailbox');
        if ($enabled && !$skipCreate) {
            $this->addOptionStack(
                $options,
                'create_mailbox',
                __('Create mailbox labels (0.5-2kg)'),
                $this->urlPath . 'create',
                ['method_override' => 'mailbox', 'mailbox_type' => 'no_envelope']
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/create_envelope');
        if ($enabled && !$skipCreate) {
            $this->addOptionStack(
                $options,
                'create_envelope',
                __('Create envelope labels (50-500g)'),
                $this->urlPath . 'create',
                ['method_override' => 'mailbox', 'mailbox_type' => 'envelope']
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/create_dhl_only');
        if ($enabled && !$skipCreate) {
            $this->addOptionStack(
                $options,
                'create_dhl_only',
                __('Create labels (only for DHL shipping methods, excluding mailbox and envelope)'),
                $this->urlPath . 'create',
                ['dhlparcel_only' => 'true']
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/create_mailbox_dhl_only');
        if ($enabled && !$skipCreate) {
            $this->addOptionStack(
                $options,
                'create_mailbox_dhl_only',
                __('Create mailbox labels (0.5-2kg, only for DHL shipping methods)'),
                $this->urlPath . 'create',
                ['method_override' => 'mailbox', 'dhlparcel_only' => 'true', 'mailbox_type' => 'no_envelope']
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/create_envelope_dhl_only');
        if ($enabled && !$skipCreate) {
            $this->addOptionStack(
                $options,
                'create_envelope_dhl_only',
                __('Create envelope labels (50-500g, only for DHL shipping methods)'),
                $this->urlPath . 'create',
                ['method_override' => 'envelope', 'dhlparcel_only' => 'true', 'mailbox_type' => 'envelope']
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/download');
        if ($enabled) {
            $options[] = $this->createOption(
                'download',
                __('Download labels'),
                $this->urlPath . 'download'
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/print');
        $printServiceEnabled = $this->helper->getConfigData('usability/printing_service/enable');
        if ($enabled && $printServiceEnabled) {
            $options[] = $this->createOption(
                'print',
                __('Print labels'),
                $this->urlPath . 'print'
            );
        }

        if (empty($options)) {
            $options = [];
            $options[] = $this->createOption(
                'disabled',
                __('No DHL Parcel bulk operations enabled. Click here to go to the settings page. Bulk operations can be found in the Usability tab.'),
                'admin/system_config/edit/section/carriers/#carriers_dhlparcel'
            );
        }

        return $options;
    }

    protected function prepareData()
    {

        foreach ($this->data as $key => $value) {
            switch ($key) {
                case 'urlPath':
                    $this->urlPath = $value;
                    break;
                case 'skipCreate':
                    $this->skipActions[] = 'create';
                    break;
                default:
                    $this->additionalData[$key] = $value;
                    break;
            }
        }
    }

    protected function addOptionStack(&$options, $id, $label, $routePath = null, $routeParams = null)
    {
        $options[] = $this->createOption(
            $id,
            $label,
            $routePath,
            $routeParams
        );

        if ($this->helper->getConfigData('usability/bulk/create_service_saturday')) {
            $options[] = $this->createOption(
                $id . '_service_saturday',
                $label . ' + ' . __('Service: Saturday'),
                $routePath,
                $this->additionalParams($routeParams, ['service_saturday' => 'true'])
            );
        }

        if ($this->helper->getConfigData('usability/bulk/create_service_sdd')) {
            $options[] = $this->createOption(
                $id . '_service_sdd',
                $label . ' + ' . __('Service: Same-day delivery'),
                $routePath,
                $this->additionalParams($routeParams, ['service_sdd' => 'true'])
            );
        }
    }

    protected function createOption($id, $label, $routePath = null, $routeParams = null)
    {
        $option = [
            'type' => 'dhlparcel_bulk_' . $id,
            'label' => $label,
        ];

        if ($routePath) {
            $option['url'] = $this->urlBuilder->getUrl($routePath, $routeParams);
        }

        $option = array_merge_recursive($option, $this->additionalData);

        return $option;
    }

    protected function additionalParams($routeParams, $pair)
    {
        if (!is_array($routeParams) && !is_array($pair)) {
            return $routeParams;
        }
        if (!is_array($routeParams)) {
            return $pair;
        }
        if (!is_array($pair)) {
            return $routeParams;
        }
        return $routeParams + $pair;
    }
}
