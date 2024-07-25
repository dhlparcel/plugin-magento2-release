<?php
/**
 * Dhl Shipping
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to
 * newer versions in the future.
 *
 * PHP version 5.6+
 *
 * @category  DHLParcel
 * @package   DHLParcel\Shipping
 * @author    Ron Oerlemans <ron.oerlemans@dhl.com>
 * @copyright 2017 DHLParcel
 * @link      https://www.dhlecommerce.nl/
 */

namespace DHLParcel\Shipping\Controller\Adminhtml\Shipment;

use DHLParcel\Shipping\Model\Piece as Piece;
use DHLParcel\Shipping\Model\Service\Label as LabelService;
use DHLParcel\Shipping\Model\PieceFactory as PieceFactory;
use DHLParcel\Shipping\Model\ResourceModel\Piece as PieceResource;

use DHLParcel\Shipping\Model\Service\Notification as NotificationService;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Framework\Exception\NoSuchEntityException;

class Labelrequest extends \Magento\Backend\App\Action
{
    /**
     * @var LabelService
     */
    protected $labelService;

    /**
     * @var ShipmentRepository
     */
    protected $shipmentRepository;

    /**
     * @var PieceFactory
     */
    protected $pieceFactory;

    /**
     * @var PieceResource
     */
    protected $pieceResource;

    /**
     * @var NotificationService
     */
    protected $notificationService;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonResultFactory;

    /**
     * Labelrequest constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory
     * @param LabelService $labelService
     * @param NotificationService $notificationService
     * @param ShipmentRepository $shipmentRepository
     * @param PieceFactory $pieceFactory
     * @param PieceResource $pieceResource
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        LabelService $labelService,
        NotificationService $notificationService,
        ShipmentRepository $shipmentRepository,
        PieceFactory $pieceFactory,
        PieceResource $pieceResource
    ) {
        $this->labelService = $labelService;
        $this->notificationService = $notificationService;
        $this->shipmentRepository = $shipmentRepository;
        $this->pieceFactory = $pieceFactory;
        $this->pieceResource = $pieceResource;
        $this->jsonResultFactory = $jsonResultFactory;

        parent::__construct($context);
    }

    public function execute()
    {
        $shipmentRequests = [];
        $shipmentId = $this->getRequest()->getParam('shipment_id');

        try {
            /** @var Shipment $shipment */
            $shipment = $this->shipmentRepository->get($shipmentId);
            if (!$shipment) {
                throw new NoSuchEntityException();
            }
        } catch (NoSuchEntityException $e) {
            $this->notificationService->error(__('Shipment not found'));
            return $this->redirectToOrder();
        } catch (InputException $e) {
            $this->notificationService->error(__('No shipment ID provided in request'));
            return $this->redirectToOrder();
        }

        try {
            $labelIds = $this->labelService->getShipmentLabelIds($shipment);
        } catch (LocalizedException $e) {
            $this->notificationService->error(__($e->getMessage()));
            return $this->redirectToOrder();
        }

        foreach ($labelIds as $labelId) {
            /** @var Piece $piece */
            $piece = $this->pieceFactory->create();
            $this->pieceResource->load($piece, $labelId, 'label_id');

            $shipmentRequests[] = json_decode($piece->getData('shipment_request'));
        }

        $result = $this->jsonResultFactory->create();
        $result->setData($shipmentRequests);

        return $result;
    }

    protected function redirectToOrder()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $url = $this->getUrl('sales/order/view', ['order_id' => $orderId]);
        return $this->resultRedirectFactory->create()->setPath($url);
    }
}
