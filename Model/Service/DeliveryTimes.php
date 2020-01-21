<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Api\Connector;
use DHLParcel\Shipping\Model\Data\DeliveryTimeFactory;
use DHLParcel\Shipping\Model\Data\Api\Response\TimeWindowFactory;
use DHLParcel\Shipping\Model\Data\TimeSelectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Config\Model\Config\Source\Locale\WeekdaysFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class DeliveryTimes
{
    const SHIPPING_PRIORITY_BACKLOG = 'shipping_priority_backlog';
    const SHIPPING_PRIORITY_SOON = 'shipping_priority_soon';
    const SHIPPING_PRIORITY_TODAY = 'shipping_priority_today';
    const SHIPPING_PRIORITY_ASAP = 'shipping_priority_asap';

    protected $helper;
    protected $connector;
    protected $deliveryTimeFactory;
    protected $timeWindowFactory;
    protected $stockRegistry;
    protected $checkoutSession;
    /** @var \Magento\Config\Model\Config\Source\Locale\Weekdays */
    protected $weekdays;
    protected $timezone;

    public function __construct(
        Data $helper,
        Connector $connector,
        DeliveryTimeFactory $deliveryTimeFactory,
        TimeWindowFactory $timeWindowFactory,
        TimeSelectionFactory $timeSelectionFactory,
        StockRegistryInterface $stockRegistry,
        CheckoutSession $checkoutSession,
        WeekdaysFactory $weekdaysFactory,
        TimezoneInterface $timezone
    ) {
        $this->helper = $helper;
        $this->connector = $connector;
        $this->deliveryTimeFactory = $deliveryTimeFactory;
        $this->timeWindowFactory = $timeWindowFactory;
        $this->timeSelectionFactory = $timeSelectionFactory;
        $this->stockRegistry = $stockRegistry;
        $this->checkoutSession = $checkoutSession;
        $this->weekdays = $weekdaysFactory->create();
        $this->timezone = $timezone;
    }

    public function isEnabled()
    {
        $enabledSetting = $this->helper->getConfigData('delivery_times/enabled');
        return boolval($enabledSetting === '1');
    }

    public function displayFrontend()
    {
        $toBusiness = boolval($this->helper->getConfigData('label/default_to_business'));
        if ($toBusiness) {
            return false;
        }

        return true;
    }

    public function notInStock()
    {
        $stockSetting = $this->helper->getConfigData('delivery_times/in_stock_only');
        if (boolval($stockSetting !== '1')) {
            return false;
        }

        $items = $this->checkoutSession->getQuote()->getAllVisibleItems();
        foreach ($items as $item) {
            if (!$this->stockRegistry->getStockItemBySku($item->getSku())->getIsInStock()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $postalCode
     * @param $countryCode
     * @return \DHLParcel\Shipping\Model\Data\DeliveryTime[]
     */
    public function getTimeFrames($postalCode, $countryCode)
    {
        if (!$postalCode || !$countryCode) {
            return [];
        }

        $trimmedPostalCode = preg_replace('/\s+/', '', $postalCode);

        $timeWindowsResponse = $this->connector->get('time-windows', [
            'countryCode' => $countryCode,
            'postalCode' => strtoupper($trimmedPostalCode),
        ]);

        if (!$timeWindowsResponse || !is_array($timeWindowsResponse) || empty($timeWindowsResponse)) {
            return [];
        }

        $deliveryTimes = [];
        foreach ($timeWindowsResponse as $timeWindowData) {
            $timeWindow = $this->timeWindowFactory->create(['automap' => $timeWindowData]);
            $deliveryTime = $this->parseTimeWindow($timeWindow->deliveryDate, $timeWindow->startTime, $timeWindow->endTime, $timeWindow);
            $deliveryTimes[] = $deliveryTime;
        }

        return $deliveryTimes;
    }

    /**
     * @param $sourceDeliveryDate
     * @param $sourceStartTime
     * @param $sourceEndTime
     * @param \DHLParcel\Shipping\Model\Data\Api\Response\TimeWindow $timeWindow
     * @param string $compare
     * @return \DHLParcel\Shipping\Model\Data\DeliveryTime
     */
    public function parseTimeWindow($sourceDeliveryDate, $sourceStartTime, $sourceEndTime, $timeWindow = null)
    {
        $timezoneString = $this->timezone->getConfigTimezone();
        $timezone = new \DateTimeZone($timezoneString);

        $deliveryDate = date_create_from_format('d-m-Y Hi', $sourceDeliveryDate . ' ' . $sourceStartTime, $timezone);
        $deliveryDateEnd = date_create_from_format('d-m-Y Hi', $sourceDeliveryDate . ' ' . $sourceEndTime, $timezone);

        if (!$deliveryDate) {
            return null;
        }

        $date = $deliveryDate->format('D. j M.');
        $weekDay = $deliveryDate->format('w');
        $day = $deliveryDate->format('w');
        $month = $deliveryDate->format('n');
        $year = $deliveryDate->format('Y');
        $startTime = $deliveryDate->format('H:i');
        $endTime = $deliveryDateEnd->format('H:i');

        $identifier = $this->getIdentifier($sourceDeliveryDate, $sourceStartTime, $sourceEndTime);

        return $this->deliveryTimeFactory->create(['automap' => [
            'source' => $timeWindow,

            'date'    => $date,
            'weekDay' => $weekDay,
            'day'     => $day,
            'month'   => $month,
            'year'    => $year,

            'startTime' => $startTime,
            'endTime'   => $endTime,

            'displayLabel' => sprintf('%1$s (%2$s) - (%3$s)', $date, $startTime, $endTime),
            'identifier' => $identifier
        ]]);
    }

    /**
     * @param \DHLParcel\Shipping\Model\Data\DeliveryTime[]
     * @param bool $dayTime
     * @return \DHLParcel\Shipping\Model\Data\DeliveryTime[]
     */
    public function filterTimeFrames($deliveryTimes, $dayTime = true)
    {
        $dayInSeconds = 24 * 60 * 60;
        $filteredTimes = [];

        $cutoffGeneral = $this->getCutoffTimestamp();
        $todayMidnightTimestamp = $this->timezone->scopeDate()->getTimestamp() + $dayInSeconds - 1;

        $displayDays = intval($this->helper->getConfigData('delivery_times/display_days'));
        $displayDays += 1; // When setting '1 display day' for example, to make tomorrow available, you actually add 2 days. One for today, one for tomorrow. Thus you always add this one additional day to the check.
        $maxTimestamp = $this->timezone->scopeDate()->modify('+'.$displayDays.' day')->getTimestamp();

        $shippingDays = $this->getShippingDays();

        if (empty($shippingDays)) {
            return [];
        }

        $timezoneString = $this->timezone->getConfigTimezone();
        $timezone = new \DateTimeZone($timezoneString);

        foreach ($deliveryTimes as $deliveryTime) {
            $datetime = date_create_from_format('d-m-Y Hi', $deliveryTime->source->deliveryDate . ' ' . $deliveryTime->source->startTime, $timezone);
            $timestamp = $this->timezone->scopeDate(null, $datetime->format('Y-m-d H:i:s'), true)->getTimestamp();

            if ($timestamp < $todayMidnightTimestamp) {
                continue;
            }

            if ($timestamp > $maxTimestamp) {
                continue;
            }

            if ($cutoffGeneral !== null) {
                if ($this->validateWithShippingDays($cutoffGeneral, $timestamp, $shippingDays)) {
                    // This is an intentional ambiguous check, due to the lack of strict regulations on the type of input from the Time Window API so far
                    // Check if end time is AFTER 18:00 (int check), or is exactly 00:00
                    if (intval($deliveryTime->source->startTime) > 1400
                        && (intval($deliveryTime->source->endTime) > 1800 || $deliveryTime->source->endTime === '0000')) {
                        // Evening
                        if ($dayTime !== true) {
                            $filteredTimes[] = $deliveryTime;
                        }
                    } else {
                        // Day
                        if ($dayTime !== false) {
                            $filteredTimes[] = $deliveryTime;
                        }
                    }
                }
            }
        }

        return $filteredTimes;
    }

    public function showSameday()
    {
        if ($this->isEnabled()) {
            $cutoffSetting = $this->helper->getConfigData('shipping_methods/sameday/cutoff');
            $cutoffHour = intval($cutoffSetting);
        } else {
            $cutoffHour = 18; // Default to 18:00 if not using cutoff setting
        }

        $currentHour = intval($this->timezone->scopeDate(null, null, true)->format('G'));

        $cutoff = boolval($currentHour >= $cutoffHour);
        if ($cutoff) {
            return false;
        }

        $enabled = $this->helper->getConfigData('shipping_methods/sameday/enabled');
        if (!$enabled) {
            return false;
        }

        return true;
    }

    public function showPriority()
    {
        $enabled = $this->isEnabled();
        $sameDayEnabled = $this->helper->getConfigData('shipping_methods/sameday/enabled');
        if (!$enabled && !$sameDayEnabled) {
            return false;
        }

        return true;
    }

    public function saveSamedaySelection($order)
    {
        $date = $this->timezone->scopeDate(null, null, true)->format('d-m-Y');
        $startTime = '1800';
        $endTime = '2100';

        $this->saveTimeSelection($order, $date, $startTime, $endTime);
    }

    public function saveTimeSelection($order, $date, $startTime, $endTime)
    {
        if (empty($order) || !$order instanceof \Magento\Sales\Api\Data\OrderInterface) {
            return;
        }

        if (empty($date) || empty($startTime) || empty($endTime)) {
            return;
        }

        /** @var \DHLParcel\Shipping\Model\Data\TimeSelection $timeSelection */
        $timeSelection = $this->timeSelectionFactory->create();
        $timeSelection->date = $date;
        $timeSelection->startTime = $startTime;
        $timeSelection->endTime = $endTime;

        $timezoneString = $this->timezone->getConfigTimezone();
        $timezone = new \DateTimeZone($timezoneString);

        $datetime = date_create_from_format('d-m-Y Hi', $date . ' ' . $startTime, $timezone);
        $timeSelection->timestamp = $this->timezone->scopeDate(null, $datetime->format('Y-m-d H:i:s'), true)->getTimestamp();

        $order->setData('dhlparcel_shipping_deliverytimes_selection', $timeSelection->toJSON());
        $order->setData('dhlparcel_shipping_deliverytimes_priority', 9999999999 - intval($timeSelection->timestamp)); // Compatible up to year 2286
    }

    /**
     * @param $order
     * @return \DHLParcel\Shipping\Model\Data\TimeSelection
     */
    public function getTimeSelection($order)
    {
        if (empty($order) || !$order instanceof \Magento\Sales\Api\Data\OrderInterface) {
            return null;
        }

        $timeSelectionJson = $order->getData('dhlparcel_shipping_deliverytimes_selection');
        if (empty($timeSelectionJson)) {
            return null;
        }

        $timeSelectionData = json_decode($timeSelectionJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $this->timeSelectionFactory->create(['automap' => $timeSelectionData]);
    }

    public function getShippingAdviceClass($selectedTimestamp)
    {
        $shippingPriority = $this->getShippingPriority($selectedTimestamp);

        switch ($shippingPriority) {
            case self::SHIPPING_PRIORITY_TODAY:
                return 'dhlparcel-shipping-advice-today';
                break;

            case self::SHIPPING_PRIORITY_SOON:
                return 'dhlparcel-shipping-advice-soon';
                break;

            case self::SHIPPING_PRIORITY_ASAP:
                return 'dhlparcel-shipping-advice-asap';
                break;

            default:
                return 'dhlparcel-shipping-advice-backlog';
        }
    }

    public function getTimeLeft($timestamp)
    {
        $currentTimestamp = $this->timezone->scopeDate(null, null, true)->getTimestamp();
        if ($currentTimestamp > $timestamp) {
            return null;
        }
        return $this->humanTimeDiff($currentTimestamp, $timestamp);
    }

    public function getShippingAdvice($selectedTimestamp)
    {
        $currentTimestamp = $this->timezone->scopeDate(null, null, true)->getTimestamp();
        $shippingPriority = $this->getShippingPriority($selectedTimestamp);

        switch ($shippingPriority) {
            case self::SHIPPING_PRIORITY_ASAP:
                return __("Send\nASAP");
                break;

            case self::SHIPPING_PRIORITY_SOON:
                return __("Send\ntomorrow");
                break;

            case self::SHIPPING_PRIORITY_BACKLOG:
                $dayInSeconds = 24 * 60 * 60;

                $currentDayDateOnly = $this->timezone->scopeDate(null, $currentTimestamp)->format('d-m-Y');
                $currentDayTimestamp = $this->timezone->scopeDate(null, $currentDayDateOnly)->getTimestamp();

                $tomorrowDayTimestamp = $currentDayTimestamp + $dayInSeconds;

                $selectedDayDateOnly = $this->timezone->scopeDate(null, $selectedTimestamp)->format('d-m-Y');
                $selectedDayTimestamp = $this->timezone->scopeDate(null, $selectedDayDateOnly)->getTimestamp();

                $daysDifferenceTimestamp = $selectedDayTimestamp - $tomorrowDayTimestamp;
                $daysBetween = floor($daysDifferenceTimestamp / $dayInSeconds);
                return sprintf(__("Send in\n%s days"), $daysBetween);
                break;

            default:
                return __("Send\ntoday");
        }
    }

    protected function getShippingPriority($selectedTimestamp)
    {
        $currentTimestamp = $this->timezone->scopeDate(null, null, true)->getTimestamp();
        if ($currentTimestamp > $selectedTimestamp) {
            return self::SHIPPING_PRIORITY_ASAP;
        }

        $dayInSeconds = 24 * 60 * 60;

        $currentDayTimestamp = $this->timezone->scopeDate()->getTimestamp();
        $tomorrowDayTimestamp = $currentDayTimestamp + $dayInSeconds;

        $selectedDayDateOnly = $this->timezone->scopeDate(null, $selectedTimestamp)->format('d-m-Y');
        $selectedDayTimestamp = $this->timezone->scopeDate(null, $selectedDayDateOnly)->getTimestamp();

        if ($currentDayTimestamp >= $selectedDayTimestamp) {
            return self::SHIPPING_PRIORITY_ASAP;
        }

        if ($tomorrowDayTimestamp < $selectedDayTimestamp) {
            $daysDifferenceTimestamp = $selectedDayTimestamp - $tomorrowDayTimestamp;
            $dayInSeconds = 24 * 60 * 60;
            $daysBetween = floor($daysDifferenceTimestamp / $dayInSeconds);

            if ($daysBetween == 1) {
                return self::SHIPPING_PRIORITY_SOON;
            }

            return self::SHIPPING_PRIORITY_BACKLOG;
        }

        return self::SHIPPING_PRIORITY_TODAY;
    }

    protected function getCutoffTimestamp()
    {
        $dayInSeconds = 24 * 60 * 60;
        $cutoffSetting = $this->helper->getConfigData('delivery_times/cutoff');
        $cutoffHour = intval($cutoffSetting);
        $currentHour = intval($this->timezone->scopeDate(null, null, true)->format('G'));

        $cutoff = boolval($currentHour >= $cutoffHour);
        $currentTimestamp = $this->timezone->scopeDate()->getTimestamp() - 1;

        $transitDaysSetting = $this->helper->getConfigData('delivery_times/transit_days');
        $days = intval($transitDaysSetting);
        $days += $cutoff ? 1 : 0;
        $addDays = $dayInSeconds * $days;

        $cutoffTimestamp = $currentTimestamp + $addDays;

        return $cutoffTimestamp;
    }

    protected function getShippingDays()
    {
        $shippingDaysSetting = $this->helper->getConfigData('delivery_times/shipping_days');
        $shippingDaysArray = array_map('trim', explode(',', $shippingDaysSetting));

        $weekdays = $this->weekdays->toOptionArray();

        $shippingDays = [];
        foreach ($weekdays as $weekday) {
            $number = intval($weekday['value']);
            $available = boolval(in_array($number, $shippingDaysArray));
            if ($number === 0) {
                // Convert Sunday to equal date:N value of Sunday
                $number = 7;
            }
            $shippingDays[$number] = $available;
        }

        return $shippingDays;
    }

    protected function validateWithShippingDays($minimumTimestamp, $timestamp, $shippingDays)
    {
        // First check if the day before the select date is a shipping day. It will be impossible to deliver on time if not delivered the day before.
        // TODO Note, currently using a hardcoded check for Sundays. Drop off timing does not work for Sundays.
        $dayInSeconds = 24 * 60 * 60;
        $dayBeforeTimeStamp = $timestamp - $dayInSeconds;

        $dateBeforeCode = intval($this->timezone->scopeDate(null, $dayBeforeTimeStamp)->format('N'));
        if (($shippingDays[$dateBeforeCode] !== true && $dateBeforeCode != 7) || ($dateBeforeCode == 7 && $shippingDays[6] !== true)) {
            return false;
        }

        $timestampTodayCheck = $this->timezone->scopeDate()->getTimestamp() - 1;
        $timestampDifference = $timestamp - $timestampTodayCheck;
        if ($timestampDifference < 0) {
            // Unknown validation, shipping day is lower than current timestamp
            return false;
        }

        $dayInSeconds = 24 * 60 * 60;
        $daysBetween = floor($timestampDifference / $dayInSeconds);

        if ($daysBetween > 30) {
            // In case invalid timestamps are given, prevent endless loops and fail the validation
            return false;
        }

        $additionalDays = 0;
        for ($dayCheck = 0; $dayCheck < $daysBetween; $dayCheck++) {
            $theDay = intval($this->timezone->scopeDate()->modify('+'.$dayCheck.' day')->format('N'));
            if ($shippingDays[$theDay] !== true) {
                $additionalDays++;
            }
        }

        // Add the additional days to the minimum timestamp
        $minimumPlusDays = $additionalDays * $dayInSeconds;
        $minimumTimestamp = $minimumPlusDays + $minimumTimestamp;

        if ($minimumTimestamp > $timestamp) {
            return false;
        }

        return true;
    }

    protected function getIdentifier($date, $start_time, $end_time)
    {
        return $date . '___' . $start_time . '___' . $end_time;
    }

    /**
     * Ported from WC
     *
     * @param $from
     * @param string $to
     * @return string
     */
    protected function humanTimeDiff($from, $to = '')
    {
        $minuteInSeconds = 60;
        $hourInSeconds = 60 * $minuteInSeconds;
        $dayInSeconds = 24 * $hourInSeconds;
        $weekInSeconds = 7 * $dayInSeconds;
        $monthInSeconds = 30 * $dayInSeconds;
        $yearInSeconds = 365 * $dayInSeconds;

        if (empty($to)) {
            $to = time();
        }

        $diff = (int)abs($to - $from);

        if ($diff < $hourInSeconds) {
            $mins = round($diff / $minuteInSeconds);
            if ($mins <= 1) {
                $mins = 1;
            }
            $since = sprintf(__('%s min(s)'), $mins);
        } elseif ($diff < $dayInSeconds && $diff >= $hourInSeconds) {
            $hours = round($diff / $hourInSeconds);
            if ($hours <= 1) {
                $hours = 1;
            }
            $since = sprintf(__('%s hour(s)'), $hours);
        } elseif ($diff < $weekInSeconds && $diff >= $dayInSeconds) {
            $days = round($diff / $dayInSeconds);
            if ($days <= 1) {
                $days = 1;
            }
            $since = sprintf(__('%s day(s)'), $days);
        } elseif ($diff < $monthInSeconds && $diff >= $weekInSeconds) {
            $weeks = round($diff / $weekInSeconds);
            if ($weeks <= 1) {
                $weeks = 1;
            }
            $since = sprintf(__('%s week(s)'), $weeks);
        } elseif ($diff < $yearInSeconds && $diff >= $monthInSeconds) {
            $months = round($diff / $monthInSeconds);
            if ($months <= 1) {
                $months = 1;
            }
            $since = sprintf(__('%s month(s)'), $months);
        } elseif ($diff >= $yearInSeconds) {
            $years = round($diff / $yearInSeconds);
            if ($years <= 1) {
                $years = 1;
            }
            $since = sprintf(__('%s year(s)'), $years);
        }

        return $since;
    }
}
