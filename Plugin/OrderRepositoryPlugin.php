<?php

namespace DHLParcel\Shipping\Plugin;

use DHLParcel\Shipping\Model\Service\DeliveryTimes as DeliveryTimesService;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderExtensionInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use DHLParcel\Shipping\Model\Service\DeliveryServices as DeliveryServicesService;
use DHLParcel\Shipping\Model\Service\Preset as PresetService;

class OrderRepositoryPlugin
{
    protected $presetService;
    protected $deliveryServicesService;
    protected $extensionFactory;
    protected $deliveryTimesService;

    /**
     * @param PresetService           $presetService
     * @param DeliveryServicesService $deliveryServicesService
     * @param OrderExtensionFactory   $extensionFactory
     * @param DeliveryTimesService    $deliveryTimesService
     */
    public function __construct(
        PresetService $presetService,
        DeliveryServicesService $deliveryServicesService,
        OrderExtensionFactory $extensionFactory,
        DeliveryTimesService $deliveryTimesService
    ) {
        $this->presetService = $presetService;
        $this->deliveryServicesService = $deliveryServicesService;
        $this->extensionFactory = $extensionFactory;
        $this->deliveryTimesService = $deliveryTimesService;
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

    /**
     * @param OrderInterface $order
     */
    protected function extendAttributes($order)
    {
        $extensionAttributes = $order->getExtensionAttributes();
        $extensionAttributes = $extensionAttributes ?: $this->extensionFactory->create();

        $servicePointId = $order->getData('dhlparcel_shipping_servicepoint_id');
        $extensionAttributes->setData('dhlparcel_shipping_servicepoint_id', $servicePointId);

        $shippingMethodKey = $this->presetService->getMethodKey($order);
        $options = array_keys($this->presetService->getOptions($shippingMethodKey));
        $options += $this->deliveryServicesService->getSelection($order, true);

        $optionsString = implode(',', $options);
        $extensionAttributes->setData('dhlparcel_shipping_checkout_options', $optionsString);

        $timeSelection = $this->deliveryTimesService->getTimeSelection($order);
        $datePreference = $timeSelection ? date_create_from_format('d-m-Y', $timeSelection->date) : null;
        if ($datePreference) {
            $extensionAttributes->setData('dhlparcel_shipping_connectors_date_preference', $datePreference->format('Y-m-d'));
        }

        $order->setExtensionAttributes($extensionAttributes);
    }
}
