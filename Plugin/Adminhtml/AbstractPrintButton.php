<?php

namespace DHLParcel\Shipping\Plugin\Adminhtml;

use DHLParcel\Shipping\Helper\Data;

abstract class AbstractPrintButton
{
    protected $helper;

    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    protected function addButtons(\Magento\Backend\Block\Widget\ContainerInterface $subject, $shipmentId)
    {
        $hideDownload = $this->helper->getConfigData('usability/printing_service/hide_download');
        if (!$hideDownload) {
            $url = $subject->getUrl('dhlparcel_shipping/shipment/download', ['shipment_id' => $shipmentId]);
            $subject->addButton(
                'dhlparcel_shipping_download',
                [
                    'label'   => __('Download DHL Labels'),
                    'class'   => 'dhlparcel_shipping_print',
                    'onclick' => 'window.open(\'' . $url . '\')'
                ],
                101
            );
        }

        $enablePrint = $this->helper->getConfigData('usability/printing_service/enable');
        if ($enablePrint) {
            $url = $subject->getUrl('dhlparcel_shipping/shipment/print', ['shipment_id' => $shipmentId]);
            $subject->addButton(
                'dhlparcel_shipping_print',
                [
                    'label'   => __('Print DHL Labels'),
                    'class'   => 'dhlparcel_shipping_print',
                    'onclick' => "setLocation('$url')"
                ],
                102
            );
        }
    }
}
