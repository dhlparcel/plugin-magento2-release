<?php

namespace DHLParcel\Shipping\Controller\Adminhtml\Bulk;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Config\Source\BulkNotification;
use DHLParcel\Shipping\Model\Exception\LabelNotFoundException;
use DHLParcel\Shipping\Model\Exception\NoTrackException;
use DHLParcel\Shipping\Model\Exception\ShipmentNoLabelsException;
use DHLParcel\Shipping\Model\Service\Label as LabelService;
use DHLParcel\Shipping\Model\Service\Notification as NotificationService;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use setasign\Fpdi\FpdiException;

class Download extends \Magento\Backend\App\Action
{
    protected $helper;
    protected $labelService;
    protected $orderRepository;
    protected $shipmentRepository;
    protected $notificationService;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        Data $helper,
        LabelService $labelService,
        NotificationService $notificationService,
        OrderRepositoryInterface $orderRepository,
        ShipmentRepositoryInterface $shipmentRepository
    ) {
        $this->helper = $helper;
        $this->notificationService = $notificationService;
        $this->labelService = $labelService;
        $this->orderRepository = $orderRepository;
        $this->shipmentRepository = $shipmentRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        $success = [];
        $errors = [];

        if ($this->_request->getParam('create_and_download')) {
            $redirectPath = 'sales/order/';
            $orderIds = json_decode(base64_decode($this->_request->getParam('create_and_download')));
            $labels = $this->processOrderIds($orderIds, $success, $errors);
        } elseif ($this->_request->getParam('namespace') === 'sales_order_grid') {
            $redirectPath = 'sales/order/';
            $orderIds = $this->_request->getParam('selected');
            $labels = $this->processOrderIds($orderIds, $success, $errors);
        } elseif ($this->_request->getParam('namespace') === 'sales_order_shipment_grid') {
            $redirectPath = 'sales/shipment/';
            $shipmentIds = $this->_request->getParam('selected');
            $labels = $this->processShipmentIds($shipmentIds, $success, $errors);
        } else {
            $this->notificationService->error(__('DHL Parcel bulk action called from an invalid page'));
            return $this->resultRedirectFactory->create()->setPath('sales/order/');
        }

        $successCount = count($success);
        $errorCount = count($errors);
        $labelCount = count($labels);

        if ($labelCount === 0) {
            $this->notificationService->error(__('None of the selected order(s) have DHL Parcel labels'));
            return $this->resultRedirectFactory->create()->setPath($redirectPath);
        }

        // Show success summary
        if ($this->helper->getConfigData('usability/bulk_reports/notification_success')) {
            if ($successCount) {
                $this->notificationService->success(__('Successfully downloaded %1 label(s) for the following orders: %2', $labelCount, implode(', ', $success)));
            }
        }

        // Show success and error summary
        if ($this->helper->getConfigData('usability/bulk_reports/notification_status')) {
            if ($successCount > 0 && $errorCount == 0) {
                $this->notificationService->notice(__('Successfully downloaded %1 order(s)', $successCount));
            }

            if ($successCount > 0 && $errorCount > 0) {
                $this->notificationService->notice(__("Successfully downloaded %1 order(s) and %2 order(s) did not have all labels downloaded due to errors", $successCount, $errorCount));
            }

            if ($successCount == 0 && $errorCount > 0) {
                $this->notificationService->notice(__('None of the %1 order(s) have downloaded all their labels due to errors', $errorCount));
            }

            if ($successCount == 0 && $errorCount == 0) {
                $this->notificationService->notice(__('Something unexpected happened, please contact your administrator', $errorCount));
            }
        }

        // Show error summary
        $errorType = $this->helper->getConfigData('usability/bulk_reports/notification_error');
        if ($errorType === BulkNotification::NOTIFICATION_STACKED) {
            $notFoundErrors = [];
            $noTrackErrors = [];
            $noLabelsErrors = [];
            $otherErrors = [];

            foreach ($errors as $orderNumber => $exceptions) {
                foreach ($exceptions as $exception) {
                    if ($exception instanceof LabelNotFoundException) {
                        $notFoundErrors[$orderNumber] = $orderNumber;
                    } elseif ($exception instanceof NoTrackException) {
                        $noTrackErrors[$orderNumber] = $orderNumber;
                    } elseif ($exception instanceof ShipmentNoLabelsException) {
                        $noLabelsErrors[$orderNumber] = $orderNumber;
                    } else {
                        $otherErrors[$orderNumber] = $orderNumber;
                    }
                }
            }

            if (!empty($notFoundErrors)) {
                $this->notificationService->error(__("Following orders have missing labels which could not be retrieved or the label ID was invalid: %1", implode(", ", $notFoundErrors)));
            }
            if (!empty($noTrackErrors)) {
                $this->notificationService->error(__("Following orders have shipping methods that do not support tracking functionality, either change the shipping method to a DHL method or contact your developers: %1", implode(", ", $noTrackErrors)));
            }
            if (!empty($noLabelsErrors)) {
                $this->notificationService->error(__("Following orders don't have any labels: %1", implode(", ", $noLabelsErrors)));
            }
            if (!empty($otherErrors)) {
                $this->notificationService->error(__("Following orders have not categorized errors: %1", implode(", ", $otherErrors)));
            }
        }

        if ($errorType == BulkNotification::NOTIFICATION_SINGLE) {
            foreach ($errors as $orderNumber => $orderErrors) {
                /** @var LocalizedException $error */
                foreach ($orderErrors as $error) {
                    $this->notificationService->error(__($orderNumber . ' ' . $error->getMessage()));
                }
            }
        }

        if ($errorType == BulkNotification::NOTIFICATION_COMBINED) {
            $orderNumbers = array_keys($errors);
            $this->notificationService->notice(__("Following orders have missing labels: %1", implode(", ", $orderNumbers)));
        }

        try {
            $pdfResponse = $this->labelService->pdfResponse($this->getResponse(), $labels);
        } catch (FpdiException $e) {
            $this->notificationService->error(__("Something went wrong when combining PDF's"));
            return $this->resultRedirectFactory->create()->setPath($redirectPath);
        }

        return $pdfResponse;
    }

    protected function processOrderIds($orderIds, &$successStorage = null, &$errorStorage = null)
    {
        if (!is_array($orderIds)) {
            return [];
        }

        $labels = [];
        foreach ($orderIds as $orderId) {
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);
            $exceptions = [];
            foreach ($order->getShipmentsCollection() as $shipment) {
                $retrievedLabels = $this->getLabels($shipment, $exceptions);
                $labels = array_merge($labels, $retrievedLabels);
            }
            if (is_array($successStorage) && count($exceptions) === 0) {
                $successStorage[] = '#' . $order->getRealOrderId();
            } elseif (is_array($errorStorage)) {
                $errorStorage['#' . $order->getRealOrderId()] = $exceptions;
            }
        }

        return $labels;
    }

    protected function processShipmentIds($shipmentIds, &$successStorage = null, &$errorStorage = null)
    {
        if (!is_array($shipmentIds)) {
            return [];
        }

        $labels = [];
        foreach ($shipmentIds as $shipmentId) {
            /** @var Shipment $shipment */
            $shipment = $this->shipmentRepository->get($shipmentId);
            $exceptions = [];
            $retrievedLabels = $this->getLabels($shipment, $exceptions);
            $labels = array_merge($labels, $retrievedLabels);
            if (is_array($successStorage) && count($exceptions) === 0) {
                $successStorage[] = '#' . $shipment->getOrder()->getRealOrderId();
            } elseif (is_array($errorStorage)) {
                $errorStorage['#' . $shipment->getOrder()->getRealOrderId()] = $exceptions;
            }
        }

        return $labels;
    }

    protected function getLabels($shipment, &$exceptionStorage = null)
    {
        $labels = [];
        try {
            $labelIds = $this->labelService->getShipmentLabelIds($shipment);
            foreach ($labelIds as $labelId) {
                try {
                    $labels[$labelId] = $this->labelService->getLabelPdf($labelId);
                } catch (\Exception $e) {
                    if (is_array($exceptionStorage)) {
                        $exceptionStorage[] = $e;
                    }
                }
            }
        } catch (\Exception $e) {
            if (is_array($exceptionStorage)) {
                $exceptionStorage[] = $e;
            }
        }
        return $labels;
    }
}
