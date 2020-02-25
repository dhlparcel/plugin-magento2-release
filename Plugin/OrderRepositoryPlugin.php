<?php

namespace DHLParcel\Shipping\Plugin;

use DHLParcel\Shipping\Model\Service\Preset as PresetService;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderExtensionInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderRepositoryPlugin
{
    protected $presetService;
    protected $extensionFactory;

    /**
     * OrderRepositoryPlugin constructor.
     * @param PresetService $presetService
     * @param OrderExtensionFactory $extensionFactory
     */
    public function __construct(
        PresetService $presetService,
        OrderExtensionFactory $extensionFactory
    ) {
        $this->presetService = $presetService;
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $order
     *
     * @return OrderInterface
     */
    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order)
    {
        $this->extendAttributes($order);
        return $order;
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $searchResult
     *
     * @return OrderSearchResultInterface
     */
    public function afterGetList(OrderRepositoryInterface $subject, OrderSearchResultInterface $searchResult)
    {
        $orders = $searchResult->getItems();
        foreach ($orders as &$order) {
            $this->extendAttributes($order);
        }
        return $searchResult;
    }

    protected function extendAttributes($order)
    {
        $extensionAttributes = $order->getExtensionAttributes();
        $extensionAttributes = $extensionAttributes ?: $this->extensionFactory->create();

        $servicePointId = $order->getData('dhlparcel_shipping_servicepoint_id');
        $extensionAttributes->setData('dhlparcel_shipping_servicepoint_id', $servicePointId);
        
        $shippingMethodKey = $this->presetService->getMethodKey($order);
        $options = implode(',', array_keys($this->presetService->getOptions($shippingMethodKey)));
        $extensionAttributes->setData('dhlparcel_shipping_checkout_options', $options);

        $order->setExtensionAttributes($extensionAttributes);
    }
}
