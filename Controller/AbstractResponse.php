<?php

namespace DHLParcel\Shipping\Controller;

abstract class AbstractResponse extends \Magento\Framework\App\Action\Action
{
    /**
     * Create an instant phtml view
     *
     * @param $id
     * @param array $data
     *
     * @return mixed
     */
    protected function getTemplate($id, $data = [])
    {
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_LAYOUT)
            ->getLayout()
            ->createBlock(
                'DHLParcel\Shipping\Block\Ajax',
                $id,
                ['data' => array_merge($data, ['template' => $id . '.phtml'])]
            )
            ->setArea(\Magento\Framework\App\Area::AREA_FRONTEND)
            ->setIsSecureMode(true)
            ->toHtml();
    }
}
