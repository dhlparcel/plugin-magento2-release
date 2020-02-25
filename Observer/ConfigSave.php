<?php

namespace DHLParcel\Shipping\Observer;

use DHLParcel\Shipping\Model\Service\Notification as NotificationService;
use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Cache\Api as ApiCache;
use DHLParcel\Shipping\Model\Api\Connector;

use Magento\Store\Model\ScopeInterface;

class ConfigSave implements \Magento\Framework\Event\ObserverInterface
{
    protected $helper;
    protected $notificationService;

    public function __construct(
        Data $helper,
        NotificationService $notificationService
    ) {
        $this->helper = $helper;
        $this->notificationService = $notificationService;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->helper->getConfigData('active')) {
            // plugin not active
            return;
        }

        if (in_array('carriers/dhlparcel/label/default_extra_assured_min', $observer->getData('changed_paths')) || 1) {
            if ($observer->getData('website')) {
                $configValue = $this->helper->getConfigData('label/default_extra_assured_min', $observer->getData('website'), ScopeInterface::SCOPE_WEBSITE);
            } elseif ($observer->getData('store')) {
                $configValue = $this->helper->getConfigData('label/default_extra_assured_min', $observer->getData('store'));
            } else {
                $configValue = $this->helper->getConfigData('label/default_extra_assured_min', 0, ScopeInterface::SCOPE_WEBSITE);
            }
            // fixes decimal numbers that use ',' as dutch decimal numbers use a comma
            $configValue = str_replace(',', '.', $configValue);
            if (strlen($configValue)) {
                if (!is_numeric($configValue)) {
                    $this->notificationService->error(__("A non number value has been input as minimum order total for extra assured: '%1'", $configValue));
                }
                if ($configValue >= 500) {
                    $this->notificationService->error(__("A value (%1) equal to or greater than 500 has been entered as minimum order total for extra assured, extra assured only assures up to €500", $configValue));
                }
            }
        }
    }
}
