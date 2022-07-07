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

        $cacheKey = $this->apiCache->createKey(0, 'authentication');

        if ($this->apiCache->load($cacheKey) !== false) {
            // valid authentication cached
            return;
        }

        // the configs here dont use
        $userId = $this->helper->getConfigData('api/user');
        $key = $this->helper->getConfigData('api/key');
        $response = (isset($userId, $key) ? $this->connector->testAuthenticate($userId, $key) : false);
        if ($response === false) {
            // invalid authentication message
            $this->notificationService->error(__('DHL Parcel extension has been turned on but user ID and API key combination is invalid'));
        } else {
            // cache valid authentication to reduce unnecessary load
            $this->apiCache->save('valid', $cacheKey, [], 900);
        }
    }
}
