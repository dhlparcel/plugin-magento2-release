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

    /**
     * Get action options
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $options = [];

        $skipCreate = in_array('create', $this->skipActions);
        $enabled = $this->helper->getConfigData('usability/bulk/create');
        if ($enabled && !$skipCreate) {
            $options[] = $this->createOption(
                'create',
                __('Create labels'),
                $this->urlBuilder->getUrl($this->urlPath . 'create')
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/create_mailbox');
        if ($enabled && !$skipCreate) {
            $options[] = $this->createOption(
                'create_mailbox',
                __('Create mailbox labels'),
                $this->urlBuilder->getUrl($this->urlPath . 'create', ['method_override' => 'mailbox'])
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/create_dhl_only');
        if ($enabled && !$skipCreate) {
            $options[] = $this->createOption(
                'create_dhl_only',
                __('Create labels (only for DHL shipping methods)'),
                $this->urlBuilder->getUrl($this->urlPath . 'create', ['dhlparcel_only' => 'true'])
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/create_mailbox_dhl_only');
        if ($enabled && !$skipCreate) {
            $options[] = $this->createOption(
                'create_mailbox_dhl_only',
                __('Create mailbox labels (only for DHL shipping methods)'),
                $this->urlBuilder->getUrl($this->urlPath . 'create', ['method_override' => 'mailbox', 'dhlparcel_only' => 'true'])
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/download');
        if ($enabled) {
            $options[] = $this->createOption(
                'download',
                __('Download labels'),
                $this->urlBuilder->getUrl($this->urlPath . 'download')
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/print');
        $printServiceEnabled = $this->helper->getConfigData('usability/printing_service/enable');
        if ($enabled && $printServiceEnabled) {
            $options[] = $this->createOption(
                'print',
                __('Print labels'),
                $this->urlBuilder->getUrl($this->urlPath . 'print')
            );
        }

        if (empty($options)) {
            $options = [];
            $options[] = $this->createOption(
                'disabled',
                __('No DHL Parcel bulk operations enabled. Click here to go to the settings page. Bulk operations can be found in the Usability tab.'),
                $this->urlBuilder->getUrl('admin/system_config/edit/section/carriers/#carriers_dhlparcel')
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

    protected function createOption($id, $label, $url = null)
    {
        $option = [
            'type'  => 'dhlparcel_bulk_' . $id,
            'label' => $label,
        ];

        if ($url) {
            $option['url'] = $url;
        }

        $option = array_merge_recursive($option, $this->additionalData);

        return $option;
    }
}
