<?php

namespace DHLParcel\Shipping\Controller\Adminhtml\Bulk;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Config\Source\BulkNotification;
use DHLParcel\Shipping\Model\Exception\LabelNotFoundException;
use DHLParcel\Shipping\Model\Exception\NoPrinterException;
use DHLParcel\Shipping\Model\Exception\NoTrackException;
use DHLParcel\Shipping\Model\Exception\ShipmentNoLabelsException;
use DHLParcel\Shipping\Model\Service\Label as LabelService;
use DHLParcel\Shipping\Model\Service\Notification as NotificationService;
use DHLParcel\Shipping\Model\Service\Printing as PrintingService;
use Magento\Framework\Exception\LocalizedException;
use Zend_Db_Expr;

class PrintAction extends \Magento\Backend\App\Action
{
    protected $helper;
    protected $labelService;
    protected $notificationService;
    protected $printingService;
    protected $orderCollectionFactory;
    protected $massActionFilter;
    protected $shipmentCollectionFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Ui\Component\MassAction\Filter $massActionFilter,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface $orderCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory $shipmentCollectionFactory,
        Data $helper,
        LabelService $labelService,
        NotificationService $notificationService,
        PrintingService $printingService
    ) {
        $this->helper = $helper;
        $this->notificationService = $notificationService;
        $this->labelService = $labelService;
        $this->printingService = $printingService;
        $this->massActionFilter = $massActionFilter;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->shipmentCollectionFactory = $shipmentCollectionFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $success = [];
        $errors = [];

        if ($this->_request->getParam('create_and_print')) {
            $redirectPath = 'sales/order/';
            $orderIds = json_decode(base64_decode($this->_request->getParam('create_and_print')));
            $labelCount = $this->processOrders($success, $errors, $orderIds);
        } elseif ($this->_request->getParam('namespace') === 'sales_order_grid') {
            $redirectPath = 'sales/order/';
            $labelCount = $this->processOrders($success, $errors);
        } elseif ($this->_request->getParam('namespace') === 'sales_order_shipment_grid') {
            $redirectPath = 'sales/shipment/';
            $labelCount = $this->processShipments($success, $errors);
        } else {
            $this->notificationService->error(__('DHL Parcel bulk action called from an invalid page'));
            return $this->resultRedirectFactory->create()->setPath('sales/order/');
        }

        $successCount = count($success);
        $errorCount = count($errors);

        if ($labelCount === 0) {
            $this->notificationService->error(__('None of the selected order(s) have DHL Parcel labels'));
            return $this->resultRedirectFactory->create()->setPath($redirectPath);
        }

        // Show success summary
        if ($this->helper->getConfigData('usability/bulk_reports/notification_success')) {
            if ($successCount) {
                $this->notificationService->success(__('Successfully printed %1 label(s) for the following orders: %2', $labelCount, implode(', ', $success)));
            }
        }

        // Show success and error summary
        if ($this->helper->getConfigData('usability/bulk_reports/notification_status')) {
            if ($successCount > 0 && $errorCount == 0) {
                $this->notificationService->notice(__('Successfully printed %1 order(s)', $successCount));
            }

            if ($successCount > 0 && $errorCount > 0) {
                $this->notificationService->notice(__("Successfully printed %1 order(s) and %2 order(s) did not have all labels printed due to errors", $successCount, $errorCount));
            }

            if ($successCount == 0 && $errorCount > 0) {
                $this->notificationService->notice(__('None of the %1 order(s) have printed all their labels due to errors', $errorCount));
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

        return $this->resultRedirectFactory->create()->setPath($redirectPath);
    }

    protected function processOrders(&$successStorage = null, &$errorStorage = null, $orderIds = [])
    {
        if (!$orderIds) {
            $collection = $this->massActionFilter->getCollection($this->orderCollectionFactory->create());
            $selected = $this->_request->getParam(\Magento\Ui\Component\MassAction\Filter::SELECTED_PARAM);
            if (!empty($selected) && is_array($selected)) {
                $collection->getSelect()->order(new Zend_Db_Expr('FIELD(entity_id,' . implode(',', $selected) . ')'));
            }
        } else {
            $collection = $this->orderCollectionFactory->create()
                                                       ->addFieldToFilter('entity_id', [ 'in' => $orderIds ]);
        }

        $labelCount = 0;
        foreach ($collection as $order) {
            $exceptions = [];
            foreach ($order->getShipmentsCollection() as $shipment) {
                $labelCount += $this->printLabels($shipment, $exceptions);
            }
            if (is_array($successStorage) && count($exceptions) === 0) {
                $successStorage[] = '#' . $order->getRealOrderId();
            } elseif (is_array($errorStorage)) {
                $errorStorage['#' . $order->getRealOrderId()] = $exceptions;
            }
        }

        return $labelCount;
    }

    protected function processShipments(&$successStorage = null, &$errorStorage = null)
    {
        $collection = $this->massActionFilter->getCollection($this->shipmentCollectionFactory->create());
        $selected = $this->_request->getParam(\Magento\Ui\Component\MassAction\Filter::SELECTED_PARAM);
        if (!empty($selected) && is_array($selected)) {
            $collection->getSelect()->order(new Zend_Db_Expr('FIELD(entity_id,' . implode(',', $selected) . ')'));
        }

        $labelCount = 0;
        foreach ($collection as $shipment) {
            $exceptions = [];
            $labelCount += $this->printLabels($shipment, $exceptions);
            if (is_array($successStorage) && count($exceptions) === 0) {
                $successStorage[] = '#' . $shipment->getOrder()->getRealOrderId();
            } elseif (is_array($errorStorage)) {
                $errorStorage['#' . $shipment->getOrder()->getRealOrderId()] = $exceptions;
            }
        }

        return $labelCount;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param null $exceptionStorage
     * @return int
     */
    protected function printLabels($shipment, &$exceptionStorage = null)
    {
        $labelIds = [];
        try {
            $labelIds = $this->labelService->getShipmentLabelIds($shipment);
            $this->printingService->sendPrintJob($shipment->getStoreId(), $labelIds);
        } catch (\Exception $e) {
            if (is_array($exceptionStorage)) {
                $exceptionStorage[] = $e;
            }
        }
        return count($labelIds);
    }
}
