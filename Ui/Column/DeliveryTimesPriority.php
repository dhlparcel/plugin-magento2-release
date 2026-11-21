<?php

namespace DHLParcel\Shipping\Ui\Column;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Service\DeliveryTimes as DeliveryTimesService;
use Magento\Sales\Api\OrderRepositoryInterface;

class DeliveryTimesPriority extends \Magento\Ui\Component\Listing\Columns\Column
{
    protected $deliveryTimesService;
    protected $orderRepository;
    protected $helper;

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        DeliveryTimesService $deliveryTimesService,
        OrderRepositoryInterface $orderRepository,
        Data $helper,
        array $components = [],
        array $data = []
    ) {
        $this->deliveryTimesService = $deliveryTimesService;
        $this->orderRepository = $orderRepository;
        $this->helper = $helper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepare()
    {
        parent::prepare();

        if (!$this->helper->getConfigData('active')) {
            $this->_data['config']['componentDisabled'] = true;
            return;
        }

        $showPriority = $this->deliveryTimesService->showPriority();
        if (!$showPriority) {
            $this->_data['config']['componentDisabled'] = true;
        }
    }

    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items']) && is_array($dataSource['data']['items']) && !empty($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $order = $this->orderRepository->get($item['entity_id']);
            $isSDD = $order->getShippingMethod() === 'dhlparcel_sameday';

            $timeSelection = $this->deliveryTimesService->getTimeSelection($order);
            $template = $this->loadTemplate($timeSelection, $isSDD);

            $item[$this->getData('name')] = $template;
        }

        return $dataSource;
    }

    protected function loadTemplate($timeSelection, $isSDD = false)
    {
        if (!$timeSelection) {
            return '';
        }

        $deliveryTime = $this->deliveryTimesService->parseTimeWindow($timeSelection->date, $timeSelection->startTime, $timeSelection->endTime);
        if (empty($deliveryTime)) {
            return '';
        }

        $timeLeft = $this->deliveryTimesService->getTimeLeft($timeSelection->timestamp);
        $shippingAdvice = $this->deliveryTimesService->getShippingAdvice($timeSelection->timestamp, $isSDD);
        $shippingAdviceClass = $this->deliveryTimesService->getShippingAdviceClass($timeSelection->timestamp, $isSDD);

        $view = [
            'time_left'             => $timeLeft,
            'delivery_time'         => $deliveryTime,
            'shipping_advice'       => $shippingAdvice,
            'shipping_advice_class' => $shippingAdviceClass,
        ];

        return $this->context
            ->getPageLayout()
            ->createBlock(\Magento\Backend\Block\Template::class, 'deliverytimes.column.' . mt_rand()) // phpcs:ignore
            ->setData($view)
            ->setTemplate('DHLParcel_Shipping::deliverytimes.column.phtml')
            ->setArea(\Magento\Framework\App\Area::AREA_ADMINHTML)
            ->setIsSecureMode(true)
            ->toHtml();
    }
}
