<?php

namespace DHLParcel\Shipping\Ui\Column;

use DHLParcel\Shipping\Model\Service\DeliveryTimes as DeliveryTimesService;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Controller\ResultFactory;

class DeliveryTimesPriority extends \Magento\Ui\Component\Listing\Columns\Column
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

            $timeSelection = $this->deliveryTimesService->getTimeSelection($order);
            $template = $this->loadTemplate($timeSelection);

            $item[$this->getData('name')] = $template;
        }

        return $dataSource;
    }

    protected function loadTemplate($timeSelection)
    {
        if (!$timeSelection) {
            return '';
        }

        $deliveryTime = $this->deliveryTimesService->parseTimeWindow($timeSelection->date, $timeSelection->startTime, $timeSelection->endTime);
        if (empty($deliveryTime)) {
            return '';
        }

        $timeLeft = $this->deliveryTimesService->getTimeLeft($timeSelection->timestamp);
        $shippingAdvice = $this->deliveryTimesService->getShippingAdvice($timeSelection->timestamp);
        $shippingAdviceClass = $this->deliveryTimesService->getShippingAdviceClass($timeSelection->timestamp);

        $view = [
            'time_left'             => $timeLeft,
            'delivery_time'         => $deliveryTime,
            'shipping_advice'       => $shippingAdvice,
            'shipping_advice_class' => $shippingAdviceClass,
        ];

        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_LAYOUT)
            ->getLayout()
            ->createBlock('Magento\Framework\View\Element\Template', 'deliverytimes.column.' . mt_rand()) // phpcs:ignore
            ->setData($view)
            ->setTemplate('DHLParcel_Shipping::deliverytimes.column.phtml')
            ->setArea(\Magento\Framework\App\Area::AREA_ADMINHTML)
            ->setIsSecureMode(true)
            ->toHtml();
    }

    public function prepare()
    {
        parent::prepare();

        $showPriority = $this->deliveryTimesService->showPriority();
        if (!$showPriority) {
            $this->_data['config']['componentDisabled'] = true;
        }
    }
}
