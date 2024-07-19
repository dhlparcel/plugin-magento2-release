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

namespace DHLParcel\Shipping\Controller\Adminhtml\Authentication;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Service\Authentication as AuthenticationService;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;

class TestAuthentication extends \Magento\Backend\App\Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::shipment';
    protected $authenticationService;
    protected $helper;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        AuthenticationService $authenticationService,
        Data $helper
    ) {
        $this->authenticationService = $authenticationService;
        $this->helper = $helper;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface
     */
    public function execute()
    {
        if ($this->getRequest()->getParam('nokey') === '1') {
            $userId = $this->helper->getConfigData('api/user');
            $apiKey = $this->helper->getConfigData('api/key');
        } else {
            // Get User Params
            $userId = $this->getRequest()->getParam('userId');
            $apiKey = $this->getRequest()->getParam('apiKey');
        }

        $authentication = $this->authenticationService->test($userId, $apiKey);

        $response = [
            'status'  => $authentication ? 'success' : 'failed',
            'message' => $authentication ? __('Authentication successful') : __('Authentication failed. Please try again or contact customer service for help'),
            'data'    => $authentication,
        ];

        return $this->resultFactory
            ->create(ResultFactory::TYPE_JSON)
            ->setData($response);
    }
}
