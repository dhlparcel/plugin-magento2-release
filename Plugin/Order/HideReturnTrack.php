<?php

namespace DHLParcel\Shipping\Plugin\Order;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Service\Returns as ReturnService;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track\Collection as TracksCollection;

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

    /**
     * @param \Magento\Sales\Model\Order $subject
     * @param TracksCollection $tracks
     * @return TracksCollection
     */
    public function afterGetTracksCollection(\Magento\Sales\Model\Order $subject, $tracks)
    {
        if (boolval($this->helper->getConfigData('usability/return_tracks/show_for_customers'))) {
            return $tracks;
        }

        // Expected the template to be at around trace #4, but depending on the amount of interceptors this might differ
        // Thus the limit is set higher to offset this
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 14);

        $hideReturnTrack = false;
        foreach ($traces as $trace) {
            if (is_array($trace) && isset($trace['file'])) {
                $searches = [
                    'email/shipment/track.phtml',
                    'frontend/templates/items.phtml',
                    'Block/Order/PrintOrder/Shipment.php'
                ];
                foreach ($searches as $search) {
                    $templateFile = $trace['file'];

                    if (substr($templateFile, -strlen($search)) === $search) {
                        $hideReturnTrack = true;
                        break 2;
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
