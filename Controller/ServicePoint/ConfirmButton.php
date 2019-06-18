<?php

namespace DHLParcel\Shipping\Controller\ServicePoint;

use DHLParcel\Shipping\Controller\AbstractResponse;

class ConfirmButton extends AbstractResponse
{

    public function execute()
    {
        return $this->resultFactory
            ->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->setData([
                'status'  => 'success',
                'data'    => [
                    'view' => $this->getTemplate('servicepoint.confirm.button', [
                    ])
                ],
                'message' => null,
            ]);
    }
}
