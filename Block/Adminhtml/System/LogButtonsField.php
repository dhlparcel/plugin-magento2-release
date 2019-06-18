<?php

namespace DHLParcel\Shipping\Block\Adminhtml\System;

class LogButtonsField extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var string
     */
    protected $_template = 'DHLParcel_Shipping::system/logbuttons.phtml';

    /**
     * Return element html
     *
     * @param  \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        unset($element);
        return $this->toHtml();
    }

    /**
     * Return ajax url for test authentication button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('dhlparcel_shipping/log/reset');
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getViewButtonHtml()
    {
        $url = $this->getUrl('dhlparcel_shipping/log/view');
        $button = $this->getLayout()
            ->createBlock(\Magento\Backend\Block\Widget\Button::class)
            ->setData([
                'label'   => __('View Log'),
                'class'   => 'dhlparcel_shipping_log_view',
                'onclick' => "window.open('$url')"
            ]);

        return $button->toHtml();
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getResetButtonHtml()
    {
        $button = $this->getLayout()
            ->createBlock(\Magento\Backend\Block\Widget\Button::class)
            ->setData([
                'id'    => 'dhlparcel_debug_log_reset',
                'label' => __('Reset log'),
            ]);

        return $button->toHtml();
    }
}
