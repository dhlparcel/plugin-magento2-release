<?php

namespace DHLParcel\Shipping\Controller\ServicePoint;

use DHLParcel\Shipping\Controller\AbstractResponse;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;

class Sync extends AbstractResponse
{

    protected $checkoutSession;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $data = $this->getRequest()->getPost();
        $servicePointId = $data->servicepoint_id ?: null;
        $servicePointCountry = $data->servicepoint_country ?: null;
        $servicePointName = $data->servicepoint_name ?: null;
        $servicePointPostcode = $data->servicepoint_postcode ?: null;

        $this->checkoutSession->setDHLParcelShippingServicePointId($servicePointId);
        $this->checkoutSession->setDHLParcelShippingServicePointCountry($servicePointCountry);
        $this->checkoutSession->setDHLParcelShippingServicePointName($servicePointName);
        $this->checkoutSession->setDHLParcelShippingServicePointPostcode($servicePointPostcode);

        $validate = boolval(!empty($servicePointId) && !empty($servicePointCountry));

        return $this->resultFactory
            ->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->setData([
                'status'  => 'success',
                'data'    => $validate,
                'message' => null
            ]);
    }
}
