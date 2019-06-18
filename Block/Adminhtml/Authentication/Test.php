<?php

namespace DHLParcel\Shipping\Block\Adminhtml\Authentication;

class Test extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var string
     */
    protected $_template = 'DHLParcel_Shipping::authentication/test.phtml';

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
        return $this->getUrl('dhlparcel_shipping/authentication/test');
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()
            ->createBlock(\Magento\Backend\Block\Widget\Button::class)
            ->setData([
                'id'    => 'dhlparcel_authentication_test',
                'label' => __('Test Authentication'),
            ]);

        return $button->toHtml();
    }
}
