<?php
/**
 * Dhl Shipping
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to
 * newer versions in the future.
 *
 * PHP version 5.6+
 *
 * @category  DHLParcel
 * @package   DHLParcel\Shipping
 * @author    Ron Oerlemans <ron.oerlemans@dhl.com>
 * @copyright 2017 DHLParcel
 * @link      https://www.dhlecommerce.nl/
 */

namespace DHLParcel\Shipping\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    /**
     * Data constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(\Magento\Framework\App\Helper\Context $context)
    {
        parent::__construct($context);
    }

    /**
     * @param $configPath
     * @param null $storeId
     * @param null $scope
     * @return mixed
     */
    public function getConfigData($configPath, $storeId = null, $scope = null)
    {
        return $this->scopeConfig->getValue(
            'carriers/dhlparcel/' . $configPath,
            $scope ?: ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
