<?php

namespace DHLParcel\Shipping\Controller\Adminhtml\Bulk;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Exception\LabelNotFoundException;
use DHLParcel\Shipping\Model\Exception\NoPrinterException;
use DHLParcel\Shipping\Model\Exception\NoTrackException;
use DHLParcel\Shipping\Model\Exception\ShipmentNoLabelsException;
use DHLParcel\Shipping\Model\Service\Label as LabelService;
use DHLParcel\Shipping\Model\Service\Notification as NotificationService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use DHLParcel\Shipping\Model\Config\Source\BulkNotification;
use DHLParcel\Shipping\Model\Service\Printing as PrintingService;

use Magento\Sales\Api\OrderRepositoryInterface;

class PrintAction extends \Magento\Backend\App\Action
{
    const ACTION_NAME = 'printlabels';
    const REDIRECT_PATH = 'sales/order/';
    protected $helper;
    protected $labelService;
    protected $orderRepository;
    protected $notificationService;
    protected $printingService;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        Data $helper,
        LabelService $labelService,
        NotificationService $notificationService,
        OrderRepositoryInterface $orderRepository,
        PrintingService $printingService
    ) {
        $this->helper = $helper;
        $this->notificationService = $notificationService;
        $this->labelService = $labelService;
        $this->orderRepository = $orderRepository;
        $this->printingService = $printingService;
        parent::__construct($context);
    }

    public function execute()
    {
        if ($this->_request->getParam('namespace') != 'sales_order_grid') {
            $this->notificationService->error(__('DHL parcel bulk action called on a non sales_order_grid page'));
            return $this->resultRedirectFactory->create()->setPath(self::REDIRECT_PATH);
        }

        $errors = [];
        $success = [];
        $labelCount = 0;
        foreach ($this->_request->getParam('selected') as $orderId) {
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);
            $exceptions = [];
            foreach ($order->getShipmentsCollection() as $shipment) {
                try {
                    $labelIds = $this->labelService->getShipmentLabelIds($shipment);
                    $this->printingService->createPrintJob($labelIds);
                    $labelCount += count($labelIds);
                } catch (\Exception $e) {
                    $exceptions[] = $e;
                }
            }
            if (count($exceptions) === 0) {
                $success[] = '#' . $order->getRealOrderId();
            } else {
                $errors['#' . $order->getRealOrderId()] = $exceptions;
            }
        }

        $successCount = count($success);
        $errorCount = count($errors);

        if ($labelCount === 0) {
            $this->notificationService->error(__('None of the selected order have DHL Parcel labels'));
            $this->resultRedirectFactory->create()->setPath(self::REDIRECT_PATH);
        }

        // Show success summary
        if ($this->helper->getConfigData('usability/bulk_reports/notification_success')) {
            if ($successCount) {
                $this->notificationService->success(__('Succesfully printed %1 label(s) for the following orders: %2', $labelCount, implode(', ', $success)));
            }
        }

        // Show success and error summary
        if ($this->helper->getConfigData('usability/bulk_reports/notification_status')) {
            if ($successCount > 0 && $errorCount == 0) {
                $this->notificationService->notice(__('Successfully printed %1 order(s)', $successCount));
            }

            if ($successCount > 0 && $errorCount > 0) {
                $this->notificationService->notice(__('Successfully printed %1 order(s) and %2 order(s) didn\'t have all labels printed due to errors', $successCount, $errorCount));
            }

            if ($successCount == 0 && $errorCount > 0) {
                $this->notificationService->notice(__('None of the %1 order(s) have printed all their labels due to errors', $errorCount));
            }

            if ($successCount == 0 && $errorCount == 0) {
                $this->notificationService->notice(__('Somewhat very unexpected happened, please contact your administrator', $errorCount));
            }
        }

        // Show error summary
        $errorType = $this->helper->getConfigData('usability/bulk_reports/notification_error');
        if ($errorType === BulkNotification::NOTIFICATION_STACKED) {
            $notFoundErrors = [];
            $noTrackErrors = [];
            $noLabelsErrors = [];
            $otherErrors = [];
            $noPrinterErrors = [];

            foreach ($errors as $orderNumber => $exceptions) {
                foreach ($exceptions as $exception) {
                    if ($exception instanceof LabelNotFoundException) {
                        $notFoundErrors[$orderNumber] = $orderNumber;
                    } elseif ($exception instanceof NoTrackException) {
                        $noTrackErrors[$orderNumber] = $orderNumber;
                    } elseif ($exception instanceof ShipmentNoLabelsException) {
                        $noLabelsErrors[$orderNumber] = $orderNumber;
                    } elseif ($exception instanceof NoPrinterException) {
                        $noPrinterErrors[$orderNumber] = $orderNumber;
                    } else {
                        $otherErrors[$orderNumber] = $orderNumber;
                    }
                }
            }

            if (!empty($notFoundErrors)) {
                $this->notificationService->error(__("Following orders have missing labels which could not be retrieved or are label id was invalid: %1", implode(", ", $notFoundErrors)));
            }
            if (!empty($noTrackErrors)) {
                $this->notificationService->error(__("Following orders have shipping methods that do not support tracking functionality, either change the shipping method to a DHL method or contact your developers: %1", implode(", ", $noTrackErrors)));
            }
            if (!empty($noLabelsErrors)) {
                $this->notificationService->error(__("Following orders dont have any labels: %1", implode(", ", $noLabelsErrors)));
            }
            if (!empty($otherErrors)) {
                $this->notificationService->error(__("Following orders have not categorized errors: %1", implode(", ", $otherErrors)));
            }
            if (!empty($noPrinterErrors)) {
                $this->notificationService->error(__("Following orders could not be printed because no valid printer has been chosen: %1", implode(", ", $noPrinterErrors)));
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

        return $this->resultRedirectFactory->create()->setPath(self::REDIRECT_PATH);
    }
}
