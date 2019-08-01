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

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Framework\Exception\NoSuchEntityException;

class Download extends \Magento\Backend\App\Action
{
    protected $labelService;
    protected $notificationService;
    protected $shipmentRepository;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        LabelService $labelService,
        NotificationService $notificationService,
        ShipmentRepository $shipmentRepository
    ) {
        $this->labelService = $labelService;
        $this->notificationService = $notificationService;
        $this->shipmentRepository = $shipmentRepository;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     * @throws \Zend_Pdf_Exception
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
            $this->notificationService->error(__('No shipment ID provided in request'));
            return $this->redirectToOrder();
        }

        try {
            $labelIds = $this->labelService->getShipmentLabelIds($shipment);
        } catch (LocalizedException $e) {
            $this->notificationService->error(__($e->getMessage()));
            return $this->redirectToOrder();
        }

        $PDFs = [];
        $failedLabels = 0;
        foreach ($labelIds as $labelId) {
            try {
                $PDFs[] = $this->labelService->getLabelPdf($labelId);
            } catch (LabelNotFoundException $e) {
                $failedLabels++;
            }
        }

        if (empty($PDFs)) {
            $this->notificationService->error(__('Unable to acquire any printable labels'));
            return $this->redirectToOrder();
        }
        if ($failedLabels > 0) {
            $this->notificationService->error(__("%1 label(s) were not printed due to errors", $failedLabels));
        }

        return $this->labelService->pdfResponse($this->getResponse(), $PDFs);
    }

    protected function redirectToOrder()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $url = $this->getUrl('sales/order/view', ['order_id' => $orderId]);
        return $this->resultRedirectFactory->create()->setPath($url);
    }
}
