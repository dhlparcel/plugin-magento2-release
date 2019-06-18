<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Model\Exception\LabelNotFoundException;
use DHLParcel\Shipping\Model\Exception\NoTrackException;
use DHLParcel\Shipping\Model\Exception\ShipmentNoLabelsException;
use DHLParcel\Shipping\Model\Service\Logic\Label as LabelLogic;
use DHLParcel\Shipping\Model\Cache\Api as ApiCache;

use Magento\Framework\App\ResponseInterface;
use Magento\Shipping\Model\Shipping\LabelGenerator;

class Label
{
    protected $apiCache;
    protected $labelLogic;
    protected $labelGenerator;

    /**
     * Label constructor.
     * @param ApiCache $apiCache
     * @param LabelLogic $labelLogic
     * @param LabelGenerator $labelGenerator
     */
    public function __construct(
        ApiCache $apiCache,
        LabelLogic $labelLogic,
        LabelGenerator $labelGenerator
    ) {
        $this->apiCache = $apiCache;
        $this->labelLogic = $labelLogic;
        $this->labelGenerator = $labelGenerator;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return array
     * @throws NoTrackException
     * @throws ShipmentNoLabelsException
     */
    public function getShipmentLabelIds($shipment)
    {
        if (!is_callable([$shipment, 'getTracks'])) {
            throw new NoTrackException(__("unable to use tracks and thus does not have labels for shipment %1", $shipment->getId()));
        }
        $labelIds = $this->labelLogic->getShipmentLabelIds($shipment);
        if (empty($labelIds)) {
            throw new ShipmentNoLabelsException(__("no labels found for shipment %1, ordernumber #%2", $shipment->getId()));
        }
        return $labelIds;
    }

    /**
     * @param $labelId
     * @return bool|string
     * @throws LabelNotFoundException
     */
    public function getLabelPdf($labelId)
    {
        $cacheKey = $this->apiCache->createKey('label', ['labelId' => $labelId]);
        $raw = $this->apiCache->load($cacheKey);

        if ($raw === false) {
            if (!$labelResponse = $this->labelLogic->get($labelId)) {
                throw new LabelNotFoundException(__('unable to retrieve label %1', $labelId));
            }
            if (!$label = base64_decode($labelResponse->pdf)) {
                throw new LabelNotFoundException(__('unable to retrieve label %1', $labelId));
            }
            $this->apiCache->save($labelResponse->pdf, $cacheKey, [], 3600);
        } else {
            $label = base64_decode($raw);
        }

        return $label;
    }

    /**
     * @param ResponseInterface $response
     * @param $PDFs
     * @return ResponseInterface
     * @throws \Zend_Pdf_Exception
     */
    public function pdfResponse(ResponseInterface $response, $PDFs)
    {
        /** @var \Zend_Pdf $combinedPDF */
        $combinedPDF = $this->labelGenerator->combineLabelsPdf($PDFs);

        return $response
            ->setBody($combinedPDF->render())
            ->setHeader('Content-type', 'application/pdf', true);
    }
}
