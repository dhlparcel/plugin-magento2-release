<?php

namespace DHLParcel\Shipping\Controller\Adminhtml\Bulk;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Config\Source\BulkNotification;
use DHLParcel\Shipping\Model\Exception\FaultyServiceOptionException;
use DHLParcel\Shipping\Model\Exception\LabelCreationException;
use DHLParcel\Shipping\Model\Exception\NoTrackException;
use DHLParcel\Shipping\Model\Exception\NotShippableException;
use DHLParcel\Shipping\Model\Service\Notification as NotificationService;
use DHLParcel\Shipping\Model\Service\Order as OrderService;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class Create extends \Magento\Backend\App\Action
{

    const REDIRECT_PATH = 'sales/order/';

    protected $orderRepository;
    protected $orderService;
    protected $notificationService;
    protected $helper;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        OrderService $orderService,
        NotificationService $notificationService,
        Data $helper
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderService = $orderService;
        $this->notificationService = $notificationService;
        $this->helper = $helper;
        parent::__construct($context);
    }

    public function execute()
    {
        if ($this->_request->getParam('namespace') != 'sales_order_grid') {
            $this->notificationService->error(__('DHL Parcel bulk action called from an invalid page'));
            return $this->resultRedirectFactory->create()->setPath(self::REDIRECT_PATH);
        }

        $success = [];
        $errors = [];
        $orderIds = $this->_request->getParam('selected');
        if (is_array($orderIds)) {
            foreach ($orderIds as $orderId) {
                /** @var Order $order */
                $order = $this->orderRepository->get($orderId);
                try {
                    $this->orderService->createShipment($order);
                    $success[] = '#' . $order->getRealOrderId();
                } catch (LocalizedException $e) {
                    $errors['#' . $order->getRealOrderId()] = $e;
                }
            }
        }

        $successCount = count($success);
        $errorCount = count($errors);

        // Show success summary
        if ($this->helper->getConfigData('usability/bulk_reports/notification_success')) {
            if ($successCount) {
                $this->notificationService->success(__('Successfully created shipments and labels for following orders: %1', implode(', ', $success)));
            }
        }

        // Show success and error summary
        if ($this->helper->getConfigData('usability/bulk_reports/notification_status')) {
            if ($successCount > 0 && $errorCount == 0) {
                $this->notificationService->notice(__('Successfully created shipments and labels for %1 orders', $successCount));
            }

            if ($successCount > 0 && $errorCount > 0) {
                $this->notificationService->notice(__('Successfully created shipments and labels for %1 orders and %2 orders failed due to errors', $successCount, $errorCount));
            }

            if ($successCount == 0 && $errorCount > 0) {
                $this->notificationService->notice(__('No shipments and labels where created, %1 orders failed due to errors', $errorCount));
            }

            if ($successCount == 0 && $errorCount == 0) {
                $this->notificationService->notice(__('Something unexpected happened, please contact your administrator', $errorCount));
            }
        }

        // Show error summary
        $errorType = $this->helper->getConfigData('usability/bulk_reports/notification_error');
        if ($errorType == BulkNotification::NOTIFICATION_STACKED) {
            $faultyErrors = [];
            $createErrors = [];
            $noTrackErrors = [];
            $notShippableErrors = [];
            $otherErrors = [];

            foreach ($errors as $orderNumber => $error) {
                if ($error instanceof FaultyServiceOptionException) {
                    $faultyErrors[] = $orderNumber;
                } elseif ($error instanceof LabelCreationException) {
                    $createErrors[] = $orderNumber;
                } elseif ($error instanceof NoTrackException) {
                    $noTrackErrors[] = $orderNumber;
                } elseif ($error instanceof NotShippableException) {
                    $notShippableErrors[] = $orderNumber;
                } else {
                    $otherErrors[] = $orderNumber;
                }
            }

            if (!empty($faultyErrors)) {
                $this->notificationService->error(__("Following orders do not have a valid combination of service options and require manual creation: %1", implode(", ", $faultyErrors)));
            }
            if (!empty($createErrors)) {
                $this->notificationService->error(__("Following orders had an error in the label creation process and can be retried but may require manual creation: %1", implode(", ", $createErrors)));
            }
            if (!empty($noTrackErrors)) {
                $this->notificationService->error(__("Following orders have shipping methods that do not support tracking functionality, either change the shipping method to a DHL method or contact your developers: %1", implode(", ", $noTrackErrors)));
            }
            if (!empty($notShippableErrors)) {
                $this->notificationService->error(__("Following orders are not eligible to be shipped, or have been shipped already: %1", implode(", ", $notShippableErrors)));
            }
            if (!empty($otherErrors)) {
                $this->notificationService->error(__("Following orders have not categorized errors: %1", implode(", ", $otherErrors)));
            }
        }

        if ($errorType == BulkNotification::NOTIFICATION_SINGLE) {
            foreach ($errors as $orderNumber => $error) {
                $this->notificationService->error(__($orderNumber . ' ' . $error->getMessage()));
            }
        }

        if ($errorType == BulkNotification::NOTIFICATION_COMBINED) {
            $orderNumbers = array_keys($errors);
            $this->notificationService->notice(__("Following orders failed to create a shipment and label: %1", implode(", ", $orderNumbers)));
        }

        return $this->resultRedirectFactory->create()->setPath(self::REDIRECT_PATH);
    }
}
