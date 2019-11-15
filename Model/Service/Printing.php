<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Api\Connector;
use DHLParcel\Shipping\Model\Cache\Api as ApiCache;
use DHLParcel\Shipping\Model\Data\Api\Response\Printer;
use DHLParcel\Shipping\Model\Data\Api\Response\PrinterFactory;
use DHLParcel\Shipping\Model\Exception\LabelNotFoundException;
use DHLParcel\Shipping\Model\Exception\NoPrinterException;
use DHLParcel\Shipping\Model\UUID;
use DHLParcel\Shipping\Model\UUIDFactory;

class Printing
{
    protected $apiCache;
    protected $connector;
    protected $helper;
    protected $printerFactory;
    protected $storeManager;
    protected $uuidFactory;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        ApiCache $apiCache,
        Connector $connector,
        Data $helper,
        PrinterFactory $printerFactory,
        UUIDFactory $uuidFactory
    ) {
        $this->apiCache = $apiCache;
        $this->connector = $connector;
        $this->helper = $helper;
        $this->printerFactory = $printerFactory;
        $this->storeManager = $storeManager;
        $this->uuidFactory = $uuidFactory;
    }

    /**
     * @return Printer[]
     */
    public function getPrinters()
    {
        $cacheKey = $this->apiCache->createKey(0, 'printers');
        $json = $this->apiCache->load($cacheKey);

        if ($json === false) {
            $response = $this->connector->get('printers');
            if (!empty($response)) {
                $this->apiCache->save(json_encode($response), $cacheKey, [], 15);
            }
        } else {
            $response = json_decode($json, true);
        }

        $printers = [];
        if ($response && is_array($response)) {
            foreach ($response as $entry) {
                $printers[] = $this->printerFactory->create(['automap' => $entry]);
            }
        }

        return $printers;
    }

    /**
     * @param $storeId
     * @param array $labelIds
     * @param bool $retry
     * @return bool|mixed
     * @throws LabelNotFoundException
     * @throws NoPrinterException
     */
    public function sendPrintJob($storeId, $labelIds = [], $retry = true)
    {
        $printerId = $this->helper->getConfigData('usability/printing_service/printer', $storeId);

        if (empty($printerId)) {
            throw new NoPrinterException(__('No printer selected'));
        }

        $response = $this->connector->post('printers/' . $printerId . '/jobs', [
            'id'       => (string)$this->uuidFactory->create(),
            'labelIds' => $labelIds
        ]);

        if ($this->connector->errorCode === 404) {
            throw new NoPrinterException(__('This printer no longer exists'));
        }
        if ($this->connector->errorCode === 409 && $retry) {
            // Retries with newly formed UUID
            $response = $this->sendPrintJob($storeId, $labelIds, false);
        }
        if ($this->connector->errorCode === 400 && $retry) {
            throw new LabelNotFoundException(__('One of the labels you are trying to print is invalid'));
        }

        return $response === null;
    }
}
