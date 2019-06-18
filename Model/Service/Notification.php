<?php

namespace DHLParcel\Shipping\Model\Service;

class Notification
{
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
     * @param \Magento\Framework\Phrase $message
     * @param null $group
     * @return $this
     */
    public function success(\Magento\Framework\Phrase $message, $group = null)
    {
        $this->messageManager->addSuccessMessage($message, $group);
        return $this;
    }

    /**
     * @param \Magento\Framework\Phrase $message
     * @param null $group
     * @return $this
     */
    public function error(\Magento\Framework\Phrase $message, $group = null)
    {
        $this->messageManager->addErrorMessage($message, $group);
        return $this;
    }

    /**
     * @param \Magento\Framework\Phrase $message
     * @param null $group
     * @return $this
     */
    public function warning(\Magento\Framework\Phrase $message, $group = null)
    {
        $this->messageManager->addWarningMessage($message, $group);
        return $this;
    }

    /**
     * @param \Magento\Framework\Phrase $message
     * @param null $group
     * @return $this
     */
    public function notice(\Magento\Framework\Phrase $message, $group = null)
    {
        $this->messageManager->addNoticeMessage($message, $group);
        return $this;
    }
}
