<?php

namespace DHLParcel\Shipping\Block\Adminhtml\Order\Shipment;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Data\Api\Response\ServicePoint;
use DHLParcel\Shipping\Model\Service\Preset as PresetService;
use DHLParcel\Shipping\Model\Service\ServicePoint as ServicePointService;
use Magento\Backend\Model\UrlInterface;
use Magento\Sales\Model\OrderRepository;

class Create extends \Magento\Backend\Block\Template
{
    const INPUT_TYPE_CURRENCY = 'currency';
    const INPUT_TYPE_TEXT = 'text';
    const INPUT_TYPE_HIDDEN = 'hidden';
    const INPUT_TYPE_SELECT = 'select';
    protected $backendUrl;
    protected $helper;
    /**
     * @var array
     */
    protected $options;
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;
    protected $orderRepository;
    protected $presetService;
    protected $servicePointService;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        UrlInterface $backendUrl,
        Data $helper,
        OrderRepository $orderRepository,
        PresetService $presetService,
        ServicePointService $servicePointService,
        array $data = []
    ) {
        $this->backendUrl = $backendUrl;
        $this->helper = $helper;
        $this->orderRepository = $orderRepository;
        $this->presetService = $presetService;
        $this->servicePointService = $servicePointService;

        parent::__construct($context, $data);
    }

    public function canCreateLabel()
    {
        return boolval($this->helper->getConfigData('active', $this->getOrder()->getStoreId()));
    }

    public function createLabelByDefault()
    {
        $default = preg_match('/^dhlparcel_/', $this->getOrder()->getShippingMethod());
        if ($default) {
            return true;
        }
        return $this->helper->getConfigData('label/create_label_by_default', $this->getOrder()->getStoreId());
    }

    public function getOption($key, $bool = true)
    {
        if (!$this->options) {
            $this->options = $this->presetService->getDefaultOptions($this->getOrder());
        }

        if (key_exists($key, $this->options)) {
            if ($bool) {
                return true;
            } else {
                return $this->options[$key];
            }
        } else {
            return false;
        }
    }

    public function getBusinessSwitch()
    {
        return [
            'private'  => [
                'label'   => __('consumer'),
                'default' => boolval($this->presetService->defaultToBusiness($this->getOrder()->getStoreId()) === false)
            ],
            'business' => [
                'label'   => __('business'),
                'default' => boolval($this->presetService->defaultToBusiness($this->getOrder()->getStoreId()) === true)
            ]
        ];
    }

    /**
     * @return string
     */
    public function getDataUrl()
    {
        $address = $this->getOrder()->getShippingAddress();
        $params = [
            'country'    => $address->getCountryId(),
            'postalcode' => $address->getPostcode(),
            'store_id'   => $this->getOrder()->getStoreId()
        ];
        return $this->backendUrl->getUrl("dhlparcel_shipping/shipment/capabilities", $params) . 'audience/';
    }

    /**
     * @return array
     */
    public function getMethods()
    {
        return [
            'PS'   => [
                'label'       => __('DHL ServicePoint'),
                'description' => __('We deliver your shipment to the address of the recipient')
            ],
            'DOOR' => [
                'label'       => __('At the door'),
                'description' => __("We deliver your shipment to the recipient's nearest DHL ServicePoint")
            ],
            'BP'   => [
                'label'       => __('In the mailbox'),
                'description' => __('Delivery in the mailbox of the recipient')
            ],
        ];
    }

    public function getMethodOptions()
    {
        $rawServicePoints = $this->servicePointService->search(
            $this->order->getShippingAddress()->getPostcode(),
            $this->getOrder()->getShippingAddress()->getCountryId(),
            20
        );
        $selectedServicePointId = $this->getOrder()->getData('dhlparcel_shipping_servicepoint_id') ?: null;
        $customerSelection = !empty($selectedServicePointId);

        $inArray = false;
        foreach ($rawServicePoints as $rawServicePoint) {
            if ($rawServicePoint->id === $selectedServicePointId) {
                $inArray = true;
            }
        }

        if (!$inArray) {
            if ($customerSelection) {
                $selectedServicePoint = $this->servicePointService->get($selectedServicePointId, $this->getOrder()->getShippingAddress()->getCountryId());

                if ($selectedServicePoint) {
                    array_unshift($rawServicePoints, $selectedServicePoint);
                    $inArray = true;
                }
            }

            if (!$inArray && count($rawServicePoints) > 0) {
                $selectedServicePointId = $rawServicePoints[0]->id;
                $customerSelection = false;
            }
        }

        $servicePointOptions = [];
        foreach ($rawServicePoints as $rawServicePoint) {
            $name = $rawServicePoint->name . ' ';
            $name .= $rawServicePoint->distance ? $rawServicePoint->distance . 'm ' : '';
            if ($rawServicePoint->id === $selectedServicePointId) {
                $name .= $customerSelection ? __('(selected by customer)') : __('(default closest selection)');
                $servicePointOptions = [$rawServicePoint->id => $name] + $servicePointOptions;
            } else {
                $servicePointOptions[$rawServicePoint->id] = $name;
            }
        }

        return [
            'PS' => [
                'servicepoint_id' => [
                    'data'    => $selectedServicePointId,
                    'type'    => self::INPUT_TYPE_SELECT,
                    'options' => $servicePointOptions
                ]
            ],
        ];
    }

    public function getServiceOptions()
    {
        return [
            'REFERENCE'        => [
                'label'       => $this->presetService->getTranslation('REFERENCE'),
                'description' => __('Add a short reference for your own administration (max 15 characters).'),
                'input'       => self::INPUT_TYPE_TEXT,
                'max'         => 15
            ],
            'REFERENCE2'       => [
                'label'       => $this->presetService->getTranslation('REFERENCE2'),
                'description' => __('Add a reference for your own administration (max 70 characters).'),
                'input'       => self::INPUT_TYPE_TEXT,
                'max'         => 70
            ],
            'ADD_RETURN_LABEL' => [
                'label'       => $this->presetService->getTranslation('ADD_RETURN_LABEL'),
                'description' => __('Print an extra label for return shipments')
            ],
            'EA'               => [
                'label'       => $this->presetService->getTranslation('EA'),
                'description' => __('This option allows you to claim the value of your shipment in case of damage or loss (up to € 500.00).')
            ],
            'HANDT'            => [
                'label'       => $this->presetService->getTranslation('HANDT'),
                'description' => __('We ask for a signature on delivery.')
            ],
            'EVE'              => [
                'label'       => $this->presetService->getTranslation('EVE'),
                'description' => __('We deliver your shipment in the evening.')
            ],
            'NBB'              => [
                'label'       => $this->presetService->getTranslation('NBB'),
                'description' => __('We do not deliver at neighbours in case the recipient is not at home.')
            ],
            'INS'              => [
                'label'       => $this->presetService->getTranslation('INS'),
                'description' => __('Additional transport insurance. If the value of the goods exceeds € 50.000, please contact our Customer Service prior to shipping.'),
                'input'       => self::INPUT_TYPE_CURRENCY,
                'max'         => 1000000
            ],
            'S'                => [
                'label'       => $this->presetService->getTranslation('S'),
                'description' => __('We deliver your shipment on Saturday.')
            ],
            'EXP'              => [
                'label'       => $this->presetService->getTranslation('EXP'),
                'description' => __('We deliver your shipment before 11 AM.')
            ],
            'BOUW'             => [
                'label'       => $this->presetService->getTranslation('BOUW'),
                'description' => __('We deliver your shipment on a site under construction.')
            ],
            'EXW'              => [
                'label'       => $this->presetService->getTranslation('EXW'),
                'description' => __('Ex factory')
            ],
            'SSN'              => [
                'label'       => $this->presetService->getTranslation('SSN'),
                'description' => __('Hide original shipper and use configuration "Alternative Shipping Address for Hide Shipper Service"')
            ],
            'SDD'              => [
                'label'       => $this->presetService->getTranslation('SDD'),
                'description' => __('We will deliver your shipment today.')
            ],
            'AGE_CHECK'        => [
                'label'       => $this->presetService->getTranslation('AGE_CHECK'),
                'description' => __("The recipient's age is checked (18+)")
            ],
            'PERS_NOTE'        => [
                'label'       => $this->presetService->getTranslation('PERS_NOTE'),
                'description' => __('We send the recipient the shipment number for using track & trace. Add your own message to this e-mail:'),
                'input'       => self::INPUT_TYPE_TEXT,
                'max'         => 99999
            ],
            'BMC'              => [
                'label'       => $this->presetService->getTranslation('BMC'),
                'description' => __('We ask for a 4 digit code/QR code on delivery, which is only known to the recipient')
            ],
        ];
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        if (!$this->order) {
            try {
                $this->order = $this->orderRepository->get($this->getRequest()->getParam('order_id'));
            } catch (\Exception $e) {
                //this should technically never happen, magento will error out on its own before reaching this point
            }
        }
        return $this->order;
    }
}
