<?php

namespace DHLParcel\Shipping\Plugin;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Carrier;

class ChangeTrackTemplate
{
    /**
     * @var Carrier
     */
    public $DHLCarrier;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $productMetadata;

    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        Carrier $DHLCarrier,
        Data $helper
    ) {
        $this->productMetadata = $productMetadata;
        $this->DHLCarrier = $DHLCarrier;
        $this->helper = $helper;
    }

    public function afterGetTemplate(
        $template,
        $result
    ) {
        if ($result == "Magento_Sales::email/shipment/track.phtml"
            && boolval($this->helper->getConfigData('usability/template_overwrites/email_shipment_track'))
        ) {
            if (version_compare($this->productMetadata->getVersion(), '2.3.0', '<')) {
                $result = "DHLParcel_Shipping::email/shipment/track.phtml";
                $template->setDHLCarrier($this->DHLCarrier);
            }
        }

        return $result;
    }
}
