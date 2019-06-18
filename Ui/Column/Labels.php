<?php

namespace DHLParcel\Shipping\Ui\Column;

use DHLParcel\Shipping\Model\Piece as Piece;
use DHLParcel\Shipping\Model\PieceFactory as PieceFactory;
use DHLParcel\Shipping\Model\ResourceModel\Piece as PieceResource;
use \Magento\Framework\UrlInterface;
use \Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Sales\Api\ShipmentRepositoryInterface;

class Labels extends \Magento\Ui\Component\Listing\Columns\Column
{

    protected $pieceFactory;
    protected $pieceResource;
    protected $urlBuilder;
    protected $orderRepository;
    protected $shipmentRepository;

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        PieceFactory $pieceFactory,
        PieceResource $pieceResource,
        UrlInterface $urlBuilder,
        OrderRepositoryInterface $orderRepository,
        ShipmentRepositoryInterface $shipmentRepository,
        array $components = [],
        array $data = []
    ) {
        $this->pieceFactory = $pieceFactory;
        $this->pieceResource = $pieceResource;
        $this->urlBuilder = $urlBuilder;
        $this->orderRepository = $orderRepository;
        $this->shipmentRepository = $shipmentRepository;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items']) && is_array($dataSource['data']['items']) && !empty($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            // Check if it's a shipment or an order overview
            if (array_key_exists('shipment_status', $item)) {
                $shipments = $this->getShipmentById($item['entity_id']);
            } else {
                $shipments = $this->getShipmentsByOrderId($item['entity_id']);
            }

            $labels = [];
            foreach ($shipments as $shipment) {
                $parsedLabels = $this->getLabelLinks($shipment);
                $labels = array_merge($labels, $parsedLabels);
            }

            $item[$this->getData('name')] = implode('<br /><br />', $labels);
        }

        return $dataSource;
    }

    protected function getLabelLinks($shipment)
    {
        $labels = [];
        /** @var \Magento\Sales\Model\Order\Shipment\Track[] $tracks */
        $tracks = $shipment->getTracks();
        foreach ($tracks as $track) {
            if ($track->getCarrierCode() != 'dhlparcel') {
                continue;
            }

            $trackNumber = $track->getTrackNumber();

            /** @var Piece $piece */
            $piece = $this->pieceFactory->create();
            $this->pieceResource->load($piece, $trackNumber, 'tracker_code');

            $trackingUrl = $this->urlBuilder->getUrl('dhlparcel_shipping/shipment/download', ['shipment_id' => $shipment->getId()]);
            $trackingText = $piece->getIsReturn() ? $track->getTitle() : $trackNumber;

            $output = html_entity_decode('<span style="white-space: nowrap;">[ <a href="' . $trackingUrl . '" target="_blank">' . $trackingText . '</a> ]</span>');

            $labels[$trackNumber] = $output;
        }

        return $labels;
    }

    protected function getShipmentById($shipmentId)
    {
        $shipments = [];
        $shipments[] = $this->shipmentRepository->get($shipmentId);
        return $shipments;
    }

    protected function getShipmentsByOrderId($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        return $order->getShipmentsCollection();
    }
}
