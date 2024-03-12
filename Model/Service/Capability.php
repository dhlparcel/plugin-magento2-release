<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Helper\Data;
use DHLParcel\Shipping\Model\Api\Connector;
use DHLParcel\Shipping\Model\Cache\Api as ApiCache;
use DHLParcel\Shipping\Model\Data\Api\Request\CapabilityCheckFactory;
use DHLParcel\Shipping\Model\Data\Api\Response\Capability\ParcelType;
use DHLParcel\Shipping\Model\Data\Api\Response\CapabilityFactory;
use DHLParcel\Shipping\Model\Data\Capability\OptionFactory;
use DHLParcel\Shipping\Model\Data\Api\Response\Capability\ParcelTypeFactory;

class Capability
{
    protected $helper;
    protected $apiCache;
    protected $connector;
    protected $optionFactory;
    protected $parcelTypeFactory;
    protected $capabilityCheckFactory;
    protected $capabilityFactory;

    public function __construct(
        Data $helper,
        ApiCache $apiCache,
        Connector $connector,
        OptionFactory $optionFactory,
        ParcelTypeFactory $parcelTypeFactory,
        CapabilityCheckFactory $capabilityCheckFactory,
        CapabilityFactory $capabilityFactory
    ) {
        $this->helper = $helper;
        $this->apiCache = $apiCache;
        $this->connector = $connector;
        $this->optionFactory = $optionFactory;
        $this->parcelTypeFactory = $parcelTypeFactory;
        $this->capabilityCheckFactory = $capabilityCheckFactory;
        $this->capabilityFactory = $capabilityFactory;
    }

    public function getOptions($storeId, $toCountry = '', $toPostalCode = '', $toBusiness = false, $requestOptions = [], $returnProduct = false)
    {
        $capabilityCheck = $this->createCapabilityCheck($storeId, $toCountry, $toPostalCode, $toBusiness, $requestOptions, $returnProduct);
        $capabilities = $this->sendRequest($storeId, $capabilityCheck);

        $options = [];
        foreach ($capabilities as $capability) {
            if (!isset($capability->parcelType->key)) {
                continue;
            }

            if (empty($capability->options)) {
                continue;
            }

            foreach ($capability->options as $responseOption) {
                if (!$responseOption->key) {
                    continue;
                }

                if (!isset($options[$responseOption->key])) {
                    /** @var \DHLParcel\Shipping\Model\Data\Capability\Option $option */
                    $option = $this->optionFactory->create();
                    $option->key = $responseOption->key;
                    $option->type = [];
                    $option->exclusions = [];
                    // Set exclusions only once
                    if (isset($responseOption->exclusions) && is_array($responseOption->exclusions)) {
                        foreach ($responseOption->exclusions as $exclusion) {
                            $option->exclusions[] = $exclusion->key;
                        }
                    }
                    $options[$responseOption->key] = $option;
                } else {
                    /** @var \DHLParcel\Shipping\Model\Data\Capability\Option $option */
                    $option = $options[$responseOption->key];
                }

                // Add size to the stack of sizes, per service option
                $option->type[] = $capability->parcelType->key;
                $options[$responseOption->key] = $option;
            }
        }

        // Change to a full array
        $options = array_map(function ($value) {
            return $value->toArray();
        }, $options);

        return $options;
    }

    public function getSizes($storeId, $toCountry = '', $toPostalCode = '', $toBusiness = false, $requestOptions = [], $returnProduct = false)
    {
        $capabilityCheck = $this->createCapabilityCheck($storeId, $toCountry, $toPostalCode, $toBusiness, $requestOptions, $returnProduct);
        $capabilities = $this->sendRequest($storeId, $capabilityCheck);

        $parcelTypes = [];
        foreach ($capabilities as $capability) {
            if (!isset($capability->parcelType->key)) {
                continue;
            }

            if (!isset($capability->product->key)) {
                continue;
            }

            // Skip if already parsed
            if (isset($parcelTypes[$capability->parcelType->key])) {
                continue;
            }

            /** @var ParcelType $parcelType */
            $parcelType = $this->parcelTypeFactory->create(['automap' => $capability->parcelType->toArray()]);
            $parcelType->key = $capability->product->key;

            $parcelTypes[$capability->parcelType->key] = $parcelType->toArray();
        }

        array_multisort(array_column($parcelTypes, 'maxWeightKg'), SORT_ASC, $parcelTypes);

        return $parcelTypes;
    }

    public function setDisplayWeight($sizes)
    {
        foreach ($sizes as &$size) {
            $unit = 'kg';
            $displayMinWeight = round($size['minWeightGrams'] / 1000, 3);
            $displayMaxWeight = round($size['maxWeightGrams'] / 1000, 3);
            if ($size['maxWeightGrams'] < 1000) {
                $size['displayWeight'] = sprintf('min %s %s, max %s %s', $displayMinWeight, $unit, $displayMaxWeight, $unit);
                continue;
            }

            $size['displayWeight'] = sprintf('max %s %s', $displayMaxWeight, $unit);
        }

        return $sizes;
    }

    /**
     * @param int $storeId
     * @param $toCountry
     * @param $toPostalCode
     * @param $toBusiness
     * @param $requestOptions
     * @return \DHLParcel\Shipping\Model\Data\Api\Request\CapabilityCheck
     */
    protected function createCapabilityCheck($storeId, $toCountry, $toPostalCode, $toBusiness, $requestOptions, $returnProduct)
    {
        $accountNumber = $this->helper->getConfigData('api/account_id', $storeId);

        // Flip when we want to retreive return products
        if ($returnProduct === true) {
            $fromCountry = $toCountry;
            $fromPostalCode = $toPostalCode;

            $toCountry = $this->helper->getConfigData('shipper/country_code', $storeId);
            $toPostalCode = $this->helper->getConfigData('shipper/postal_code', $storeId);
        } else {
            $fromCountry = $this->helper->getConfigData('shipper/country_code', $storeId);
            $fromPostalCode = $this->helper->getConfigData('shipper/postal_code', $storeId);
        }

        /** @var \DHLParcel\Shipping\Model\Data\Api\Request\CapabilityCheck $capabilityCheck */
        $capabilityCheck = $this->capabilityCheckFactory->create();
        $capabilityCheck->fromCountry = trim($fromCountry ?? '');
        $capabilityCheck->fromPostalCode = strtoupper($fromPostalCode ?? '');
        $capabilityCheck->toCountry = trim($toCountry ?? '') ?: trim($fromCountry ?? '');
        $capabilityCheck->toBusiness = $toBusiness ? 'true' : 'false';
        $capabilityCheck->accountNumber = $accountNumber;
        $capabilityCheck->returnProduct = $returnProduct ? 'true' : 'false';

        if ($toPostalCode !== '') {
            $capabilityCheck->toPostalCode = strtoupper($toPostalCode ?? '');
        }

        if (is_array($requestOptions) && count($requestOptions)) {
            $capabilityCheck->option = implode(',', $requestOptions);
        }

        return $capabilityCheck;
    }

    /**
     * @param int $storeId
     * @param \DHLParcel\Shipping\Model\Data\Api\Request\CapabilityCheck $capabilityCheck
     * @return \DHLParcel\Shipping\Model\Data\Api\Response\Capability[]
     */
    protected function sendRequest($storeId, $capabilityCheck)
    {
        $cacheKey = $this->apiCache->createKey($storeId, 'capabilities', $capabilityCheck->toArray(true));
        $json = $this->apiCache->load($cacheKey);

        if ($json === false) {
            $response = $this->connector->get('capabilities/business', $capabilityCheck->toArray(true));
            if (!empty($response)) {
                $this->apiCache->save(json_encode($response), $cacheKey, [], 3600);
            }
        } else {
            $response = json_decode($json, true);
        }

        $capabilities = [];
        if ($response && is_array($response)) {
            foreach ($response as $entry) {
                $capabilities[] = $this->capabilityFactory->create(['automap' => $entry]);
            }
        }

        return $capabilities;
    }
}
