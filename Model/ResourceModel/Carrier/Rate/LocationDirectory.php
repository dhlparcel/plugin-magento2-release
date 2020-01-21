<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate;

class LocationDirectory
{
    /**
     * @var array
     */
    protected $regions;

    /**
     * @var array
     */
    protected $iso2Countries;

    /**
     * @var array
     */
    protected $iso3Countries;

    /**
     * @var \Magento\Directory\Model\ResourceModel\Country\CollectionFactory
     */
    protected $countryCollectionFactory;

    /**
     * @var \Magento\Directory\Model\ResourceModel\Region\CollectionFactory
     */
    protected $regionCollectionFactory;

    /**
     * LocationDirectory constructor.
     * @param \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollectionFactory
     * @param \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionCollectionFactory
     */
    public function __construct(
        \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollectionFactory,
        \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionCollectionFactory
    ) {
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->regionCollectionFactory = $regionCollectionFactory;
    }

    /**
     * @param string $countryCode
     * @return null|string
     */
    public function getCountryId($countryCode)
    {
        $this->loadCountries();
        $countryId = null;
        if (isset($this->iso2Countries[$countryCode])) {
            $countryId = $this->iso2Countries[$countryCode];
        } elseif (isset($this->iso3Countries[$countryCode])) {
            $countryId = $this->iso3Countries[$countryCode];
        }

        return $countryId;
    }

    /**
     * @return $this
     */
    protected function loadCountries()
    {
        if ($this->iso2Countries !== null && $this->iso3Countries !== null) {
            return $this;
        }

        $this->iso2Countries = [];
        $this->iso3Countries = [];

        /** @var $collection \Magento\Directory\Model\ResourceModel\Country\Collection */
        $collection = $this->countryCollectionFactory->create();
        foreach ($collection->getData() as $row) {
            $this->iso2Countries[$row['iso2_code']] = $row['country_id'];
            $this->iso3Countries[$row['iso3_code']] = $row['country_id'];
        }

        return $this;
    }

    /**
     * @param string $countryCode
     * @return bool
     */
    public function hasCountryId($countryCode)
    {
        $this->loadCountries();
        return isset($this->iso2Countries[$countryCode]) || isset($this->iso3Countries[$countryCode]);
    }

    /**
     * @param string $countryId
     * @param string $regionCode
     * @return bool
     */
    public function hasRegionId($countryId, $regionCode)
    {
        $this->loadRegions();
        return isset($this->regions[$countryId][$regionCode]);
    }

    /**
     * @return $this
     */
    protected function loadRegions()
    {
        if ($this->regions !== null) {
            return $this;
        }

        $this->regions = [];

        /** @var $collection \Magento\Directory\Model\ResourceModel\Region\Collection */
        $collection = $this->regionCollectionFactory->create();
        foreach ($collection->getData() as $row) {
            $this->regions[$row['country_id']][$row['code']] = (int)$row['region_id'];
        }

        return $this;
    }

    /**
     * @param int $countryId
     * @param string $regionCode
     * @return string
     */
    public function getRegionId($countryId, $regionCode)
    {
        $this->loadRegions();
        return $this->regions[$countryId][$regionCode];
    }
}
