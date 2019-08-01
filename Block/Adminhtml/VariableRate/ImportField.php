<?php

namespace DHLParcel\Shipping\Block\Adminhtml\VariableRate;

class ImportField extends \Magento\Framework\Data\Form\Element\AbstractElement
{

    protected function _construct()
    {
        parent::_construct();
        $this->setType('file');
    }

    public function getElementHtml()
    {
        $timeConditionId = $this->getId() . '_time_condition';

        $html = '<input id="' . $timeConditionId . '" type="hidden" name="' . $this->getName() . '" value="' . time() . '" />';

        if (!$conditionTarget = $this->getConditionTarget($this->getId())) {
            return '<P class="message">'.__('Invalid system.xml configuration used, field required name is import').'</P>';
        }
        $html .= <<<EndHTML
        <script>
        require(['prototype'], function(){
        Event.observe($('$conditionTarget'), 'change', checkConditionName.bind(this));
        function checkConditionName(event)
        {
            var conditionNameElement = Event.element(event);
            if (conditionNameElement && conditionNameElement.id) {
                $('$timeConditionId').value = '_' + conditionNameElement.value + '/' + Math.random();
            }
        }
        });
        </script>
EndHTML;

        $html .= parent::getElementHtml();

        return $html;
    }

    /**
     * @param $id
     * @return bool|string
     */
    protected function getConditionTarget($id)
    {
        if (preg_match('/^(?<base>\w+_)import/i', $id, $match)) {
            return $match['base'] . 'rate_condition';
        } else {
            return false;
        }
    }
}
