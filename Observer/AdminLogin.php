<?php

namespace DHLParcel\Shipping\Observer;

use DHLParcel\Shipping\Model\Service\Notification as NotificationService;
use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Cache\Api as ApiCache;
use DHLParcel\Shipping\Model\Api\Connector;

//on admin login valid authentication check
class AdminLogin implements \Magento\Framework\Event\ObserverInterface
{
    protected $apiCache;
    protected $connector;
    protected $helper;
    protected $notificationService;

    public function __construct(
        ApiCache $apiCache,
        Connector $connector,
        Data $helper,
        NotificationService $notificationService
    ) {
        $this->apiCache = $apiCache;
        $this->connector = $connector;
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

        if ($this->apiCache->load('authentication') !== false) {
            // valid authentication cached
            return;
        }

        $response = $this->connector->testAuthenticate($this->helper->getConfigData('api/user'), $this->helper->getConfigData('api/key'));
        if ($response === false) {
            // invalid authentication message
            $this->notificationService->error(__('DHL Parcel extension has been turned on but user ID and API key combination is invalid'));
        } else {
            // cache valid authentication to reduce unnecessary load
            $this->apiCache->save('valid', 'authentication', [], 86400);
        }
    }
}
