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
 * @link      https://www.dhlparcel.nl/
 */

namespace DHLParcel\Shipping\Controller\Adminhtml\Shipment;

use DHLParcel\Shipping\Model\Service\Order;
use DHLParcel\Shipping\Model\Service\Order as OrderService;
use DHLParcel\Shipping\Model\Service\Notification as NotificationService;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentRepository;

class UndoShipped extends \Magento\Backend\App\Action
{
    /** @var OrderService */
    protected $orderService;
    /** @var NotificationService */
    protected $notificationService;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $jsonResultFactory;

    /**
     * Labelrequest constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory
     * @param OrderService $orderService
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        OrderService $orderService,
        NotificationService $notificationService
    ) {
        $this->jsonResultFactory = $jsonResultFactory;
        $this->orderService = $orderService;
        $this->notificationService = $notificationService;

        parent::__construct($context);
    }

    public function execute()
    {
        $shipmentId = $this->getRequest()
            ->getParam('shipment_id');

        try {
            $this->orderService->undoShipment($shipmentId);
        } catch (NoSuchEntityException $e) {
            $this->notificationService->error(__('Shipment not found'));

            return $this->redirectToOrder();
        } catch (InputException $e) {
            $this->notificationService->error(__('No shipment ID provided in request'));

            return $this->redirectToOrder();
        }

        return $this->redirectToNewShipment();
    }

    protected function redirectToOrder()
    {
        $orderId = $this->getRequest()
            ->getParam('order_id');
        $url = $this->getUrl('sales/order/view', ['order_id' => $orderId]);

        return $this->resultRedirectFactory->create()
            ->setPath($url);
    }

    protected function redirectToNewShipment()
    {
        $orderId = $this->getRequest()
            ->getParam('order_id');
        $url = $this->getUrl('adminhtml/order_shipment/new', ['order_id' => $orderId]);

        return $this->resultRedirectFactory->create()
            ->setPath($url);
    }
}
