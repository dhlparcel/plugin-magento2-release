<?php

namespace DHLParcel\Shipping\Ui\Column;

use DHLParcel\Shipping\Model\Service\DeliveryTimes as DeliveryTimesService;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Controller\ResultFactory;

class ServicePointId extends \Magento\Ui\Component\Listing\Columns\Column
{
    protected $deliveryTimesService;
    protected $orderRepository;
    protected $resultFactory;

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        DeliveryTimesService $deliveryTimesService,
        OrderRepositoryInterface $orderRepository,
        ResultFactory $resultFactory,
        array $components = [],
        array $data = []
    ) {
        $this->deliveryTimesService = $deliveryTimesService;
        $this->orderRepository = $orderRepository;
        $this->resultFactory = $resultFactory;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items']) && is_array($dataSource['data']['items']) && !empty($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $order = $this->orderRepository->get($item['entity_id']);

            $item[$this->getData('name')] = $order->getData('dhlparcel_shipping_servicepoint_id');
        }

        return $dataSource;
    }
}
