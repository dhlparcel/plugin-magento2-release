<?php

namespace DHLParcel\Shipping\Plugin\Shipment;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\PieceFactory;
use DHLParcel\Shipping\Model\Service\Returns as ReturnService;

class HideReturnTrack
{
    /** @var Returns */
    protected $returnService;

    /** @var Data */
    protected $helper;
    
    public function __construct(
        ReturnService $returnService,
        Data $helper
    ) {
        $this->returnService = $returnService;
        $this->helper = $helper;
    }

    public function afterGetTracksCollection(\Magento\Sales\Model\Order\Shipment $subject, $tracks)
    {
        if (boolval($this->helper->getConfigData('usability/return_tracks/show_for_customers'))) {
            return $tracks;
        }

        // Expected the template to be at around trace #4, but depending on the amount of interceptors this might differ
        // Thus the limit is set higher to offset this
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 14);

        $hideReturnTrack = false;
        foreach ($traces as $trace) {
            if (!empty($trace['args']) && is_array($trace['args'])) {
                $searches = [
                    'view/frontend/templates/items.phtml'
                ];
                foreach ($searches as $search) {
                    $templateFile = $trace['args'][0];
                    if (substr($templateFile, -strlen($search)) === $search) {
                        $hideReturnTrack = true;
                        break;
                    }
                }
            }
        }

        if (!$hideReturnTrack) {
            return $tracks;
        }

        return $this->returnService->cleanupReturnTracks($tracks);
    }

    public function afterGetAllTracks(\Magento\Sales\Model\Order\Shipment $subject, $tracks)
    {
        if (boolval($this->helper->getConfigData('usability/return_tracks/show_for_customers'))) {
            return $tracks;
        }

        // Expected the template to be at around trace #4, but depending on the amount of interceptors this might differ
        // Thus the limit is set higher to offset this
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 14);

        $hideReturnTrack = false;
        foreach ($traces as $trace) {
            if (!empty($trace['args']) && is_array($trace['args'])) {
                $searches = [
                    'email/shipment/track.phtml'
                ];
                foreach ($searches as $search) {
                    $templateFile = $trace['args'][0];
                    if (substr($templateFile, -strlen($search)) === $search) {
                        $hideReturnTrack = true;
                        break;
                    }
                }
            }
        }

        if (!$hideReturnTrack) {
            return $tracks;
        }

        return $this->returnService->cleanupReturnTracks($tracks);
    }
}
