<?php

namespace DHLParcel\Shipping\Model\Service;

/**
 * Note this class has been expanded with modified functions of:
 * https://github.com/markshust/magento2-module-messages
 * To support filtered html messages
 *
 * Class Notification
 * @package DHLParcel\Shipping\Model\Service
 */
class Notification
{
    const COMPLEX_MESSAGE_CONFIGURATION = 'DHLParcelNotification';
    protected $complexTags = [
        'strong',
        'b',
        'a',
        'i',
        'br',
        'button',
        'span',
        'form',
        'p',
    ];

    /** @var \Magento\Framework\Message\ManagerInterface */
    protected $messageManager;

    /**
     * ConfigChangedSectionSalesObserver constructor.
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->messageManager = $messageManager;
    }

    /**
     * @param $message
     * @param null $group
     * @return $this
     */
    public function success($message, $group = null)
    {
        $this->messageManager->addSuccessMessage($message, $group);
        return $this;
    }

    /**
     * @param $message
     * @param null $group
     * @return $this
     */
    public function complexSuccess($message, $group = null)
    {
        $this->messageManager->addComplexSuccessMessage(self::COMPLEX_MESSAGE_CONFIGURATION, [
            'message' => $message,
            'allowedTags' => $this->complexTags,
        ], $group);
        return $this;
    }

    /**
     * @param $message
     * @param null $group
     * @return $this
     */
    public function error($message, $group = null)
    {
        $this->messageManager->addErrorMessage($message, $group);
        return $this;
    }

    /**
     * @param $message
     * @param null $group
     * @return $this
     */
    public function complexError($message, $group = null)
    {
        $this->messageManager->addComplexErrorMessage(self::COMPLEX_MESSAGE_CONFIGURATION, [
            'message' => $message,
            'allowedTags' => $this->complexTags,
        ], $group);
        return $this;
    }

    /**
     * @param $message
     * @param null $group
     * @return $this
     */
    public function warning($message, $group = null)
    {
        $this->messageManager->addWarningMessage($message, $group);
        return $this;
    }

    /**
     * @param $message
     * @param null $group
     * @return $this
     */
    public function complexWarning($message, $group = null)
    {
        $this->messageManager->addComplexWarningMessage(self::COMPLEX_MESSAGE_CONFIGURATION, [
            'message' => $message,
            'allowedTags' => $this->complexTags,
        ], $group);
        return $this;
    }

    /**
     * @param $message
     * @param null $group
     * @return $this
     */
    public function notice($message, $group = null)
    {
        $this->messageManager->addNoticeMessage($message, $group);
        return $this;
    }

    /**
     * @param $message
     * @param null $group
     * @return $this
     */
    public function complexNotice($message, $group = null)
    {
        $this->messageManager->addComplexNoticeMessage(self::COMPLEX_MESSAGE_CONFIGURATION, [
            'message' => $message,
            'allowedTags' => $this->complexTags,
        ], $group);
        return $this;
    }
}
