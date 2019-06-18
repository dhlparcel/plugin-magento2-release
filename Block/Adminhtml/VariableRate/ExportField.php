<?php

namespace DHLParcel\Shipping\Block\Adminhtml\VariableRate;

class ExportField extends \Magento\Framework\Data\Form\Element\AbstractElement
{

    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    protected $backendUrl;

    /**
     * Export constructor.
     * @param \Magento\Framework\Data\Form\Element\Factory $factoryElement
     * @param \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection
     * @param \Magento\Framework\Escaper $escaper
     * @param \Magento\Backend\Model\UrlInterface $backendUrl
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        array $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->backendUrl = $backendUrl;
    }

    public function getElementHtml()
    {
        /** @var \Magento\Backend\Block\Widget\Button $buttonBlock */
        $buttonBlock = $this->getForm()->getParent()->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        );
        $websiteId = $buttonBlock->getRequest()->getParam('website');
        $params = ['website' => $websiteId];

        $url = $this->backendUrl->getUrl("dhlparcel_shipping/system/exportrates", $params);

        $data = [
            'label'   => __('Export CSV'),
            'onclick' => "setLocation('" .
                $url .
                "conditionName/' + $('" . $this->getConditionTarget($this->getId()) . "').value + '/" .
                "shippingMethod/" . $this->getMethod() . "/" .
                "website/" . (int)$websiteId . "' )",
            'class'   => ''
        ];
        if ($this->getData('inherit') && $websiteId) {
            $data['disabled'] = 'disabled';
        }

        return $buttonBlock->setData($data)->toHtml();
    }

    /**
     * @return bool|string
     */
    protected function getMethod()
    {
        if (preg_match('/\/(?<method>[^\/]+)$/i', $this->getData('field_config/path'), $match)) {
            return $match['method'];
        } else {
            return false;
        }
    }/**
 * @param string $id
 * @return bool|string
 */
    protected function getConditionTarget($id)
    {
        if (preg_match('/^(?<base>\w+_)export/i', $id, $match)) {
            return $match['base'] . 'rate_condition';
        } else {
            return false;
        }
    }
}
