<?php

namespace DHLParcel\Shipping\Model\Service;

class CartService
{
    /**
     * Copied from Flat rate
     * vendor/magento/module-offline-shipping/Model/Carrier/Flatrate.php
     *
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return int
     */
    public function getFreeBoxesCount(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        $freeBoxes = 0;
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    $freeBoxes += $this->getFreeBoxesCountFromChildren($item);
                } elseif ($item->getFreeShipping()) {
                    $freeBoxes += $item->getQty();
                }
            }
        }
        return $freeBoxes;
    }

    /**
     * Copied from Flat rate
     * vendor/magento/module-offline-shipping/Model/Carrier/Flatrate.php
     *
     * @param mixed $item
     * @return mixed
     */
    protected function getFreeBoxesCountFromChildren($item)
    {
        $freeBoxes = 0;
        foreach ($item->getChildren() as $child) {
            if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                $freeBoxes += $item->getQty() * $child->getQty();
            }
        }
        return $freeBoxes;
    }
}
