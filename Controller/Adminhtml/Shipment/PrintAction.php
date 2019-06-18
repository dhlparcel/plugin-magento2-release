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

use DHLParcel\Shipping\Model\Exception\LabelNotFoundException;
use DHLParcel\Shipping\Model\Service\Label as LabelService;
use DHLParcel\Shipping\Model\Service\Notification as NotificationService;
use DHLParcel\Shipping\Model\Service\Printing as PrintingService;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Framework\Exception\NoSuchEntityException;

class PrintAction extends \Magento\Backend\App\Action
{
    protected $labelService;
    protected $notificationService;
    protected $shipmentRepository;
    protected $printingService;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        LabelService $labelService,
        NotificationService $notificationService,
        PrintingService $printingService,
        ShipmentRepository $shipmentRepository
    ) {
        $this->labelService = $labelService;
        $this->notificationService = $notificationService;
        $this->printingService = $printingService;
        $this->shipmentRepository = $shipmentRepository;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
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
            $this->notificationService->error(__('No shipment id provided in request'));
            return $this->redirectToOrder();
        }

        try {
            $labelIds = $this->labelService->getShipmentLabelIds($shipment);
            $this->printingService->createPrintJob($labelIds);
            $this->notificationService->success(__('succesfully printed %1 label(s)', count($labelIds)));
        } catch (LocalizedException $e) {
            $this->notificationService->error(__($e->getMessage()));
        }

        return $this->redirectToOrder();
    }

    protected function redirectToOrder()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $url = $this->getUrl('sales/order/view', ['order_id' => $orderId]);
        return $this->resultRedirectFactory->create()->setPath($url);
    }
}
