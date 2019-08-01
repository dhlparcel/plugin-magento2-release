<?php

namespace DHLParcel\Shipping\Controller\ServicePoint;

use DHLParcel\Shipping\Controller\AbstractResponse;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\Data\ShippingMethodInterface;

class Validate extends AbstractResponse
{

    protected $checkoutSession;
    protected $shippingMethod;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        CheckoutSession $checkoutSession,
        ShippingMethodInterface $shippingMethod
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->shippingMethod = $shippingMethod;
    }

    public function execute()
    {
        $servicePointId = $this->checkoutSession->getDHLParcelShippingServicePointId();
        $servicePointCountry = $this->checkoutSession->getDHLParcelShippingServicePointCountry();

        $validate = boolval($servicePointId && $servicePointCountry);

        return $this->resultFactory
            ->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->setData([
                'status'  => 'success',
                'data'    => $validate,
                'message' => null,
            ]);
    }
}
