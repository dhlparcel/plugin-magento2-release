<?php

namespace DHLParcel\Shipping\Ui\Bulk;

use DHLParcel\Shipping\Helper\Data;
use Magento\Framework\UrlInterface;

class Actions implements \Zend\Stdlib\JsonSerializable
{
    /**
     * @var array
     */
    protected $options;
    /**
     * @var array
     */
    protected $data;
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;
    protected $urlPath;
    protected $paramName;
    /**
     * @var array
     */
    protected $additionalData = [];
    /**
     * @var Data
     */
    protected $helper;

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

        $enabled = $this->helper->getConfigData('usability/bulk/create');
        if ($enabled) {
            $options[] = $this->createOption(
                'create',
                __('Create labels'),
                $this->urlBuilder->getUrl($this->urlPath . 'create')
            );
        }

        $enabled = $this->helper->getConfigData('usability/bulk/create_mailbox');
        if ($enabled) {
            $options[] = $this->createOption(
                'create_mailbox',
                __('Create mailbox labels'),
                $this->urlBuilder->getUrl($this->urlPath . 'create', ['method_override' => 'mailbox'])
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
                __('No DHL Parcel bulk operations enabled. Click here to go to the settings page. Bulk Operations can be found in the tab Usability.'),
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
                default:
                    $this->additionalData[$key] = $value;
                    break;
            }
        }
    }

    protected function createOption($action, $label, $url = null)
    {
        $option = [
            'type'  => 'dhlparcel_bulk_' . $action,
            'label' => $label,
        ];

        if ($url) {
            $option['url'] = $url;
        }

        $option = array_merge_recursive($option, $this->additionalData);

        return $option;
    }
}
