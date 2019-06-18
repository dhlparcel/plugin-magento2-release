<?php

namespace DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\CSV;

use DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\LocationDirectory;

class RowParser
{
    /**
     * @var LocationDirectory
     */
    private $locationDirectory;

    /**
     * RowParser constructor.
     * @param LocationDirectory $locationDirectory
     */
    public function __construct(LocationDirectory $locationDirectory)
    {
        $this->locationDirectory = $locationDirectory;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return [
            'website_id',
            'method_name',
            'dest_country_id',
            'dest_region_id',
            'dest_zip',
            'condition_name',
            'condition_value',
            'price',
        ];
    }

    /**
     * @param array $rowData
     * @param $rowNumber
     * @param $websiteId
     * @param $shippingMethod
     * @param $conditionShortName
     * @param $conditionFullName
     * @param ColumnResolver $columnResolver
     * @return array
     * @throws ColumnNotFoundException
     * @throws RowException
     */
    public function parse(
        array $rowData,
        $rowNumber,
        $websiteId,
        $shippingMethod,
        $conditionShortName,
        $conditionFullName,
        ColumnResolver $columnResolver
    ) {
        // validate row
        if (count($rowData) < 5) {
            throw new RowException(__('Please correct Table Rates format in the Row #%1.', $rowNumber));
        }

        $countryId = $this->getCountryId($rowData, $rowNumber, $columnResolver);
        $regionId = $this->getRegionId($rowData, $rowNumber, $columnResolver, $countryId);
        $zipCode = $this->getZipCode($rowData, $columnResolver);
        $conditionValue = $this->getConditionValue($rowData, $rowNumber, $conditionFullName, $columnResolver);
        $price = $this->getPrice($rowData, $rowNumber, $columnResolver);

        return [
            'website_id'      => $websiteId,
            'method_name'     => $shippingMethod,
            'dest_country_id' => $countryId,
            'dest_region_id'  => $regionId,
            'dest_zip'        => $zipCode,
            'condition_name'  => $conditionShortName,
            'condition_value' => $conditionValue,
            'price'           => $price,
        ];
    }

    /**
     * @param array $rowData
     * @param int $rowNumber
     * @param ColumnResolver $columnResolver
     * @return null|string
     * @throws ColumnNotFoundException
     * @throws RowException
     */
    private function getCountryId(array $rowData, $rowNumber, ColumnResolver $columnResolver)
    {
        $countryCode = strtoupper($columnResolver->getColumnValue(ColumnResolver::COLUMN_COUNTRY, $rowData));
        // validate country
        if ($this->locationDirectory->hasCountryId($countryCode)) {
            $countryId = $this->locationDirectory->getCountryId($countryCode);
        } elseif ($countryCode === '*' || $countryCode === '') {
            $countryId = '0';
        } else {
            throw new RowException(__('Please correct Country "%1" in the Row #%2.', $countryCode, $rowNumber));
        }
        return $countryId;
    }

    /**
     * @param array $rowData
     * @param int $rowNumber
     * @param ColumnResolver $columnResolver
     * @param int $countryId
     * @return int|string
     * @throws ColumnNotFoundException
     * @throws RowException
     */
    private function getRegionId(array $rowData, $rowNumber, ColumnResolver $columnResolver, $countryId)
    {
        $regionCode = $columnResolver->getColumnValue(ColumnResolver::COLUMN_REGION, $rowData);
        if ($countryId !== '0' && $this->locationDirectory->hasRegionId($countryId, $regionCode)) {
            $regionId = $this->locationDirectory->getRegionId($countryId, $regionCode);
        } elseif ($regionCode === '*' || $regionCode === '') {
            $regionId = 0;
        } else {
            throw new RowException(__('Please correct Region/State "%1" in the Row #%2.', $regionCode, $rowNumber));
        }
        return $regionId;
    }

    /**
     * @param array $rowData
     * @param ColumnResolver $columnResolver
     * @return float|int|null|string
     * @throws ColumnNotFoundException
     */
    private function getZipCode(array $rowData, ColumnResolver $columnResolver)
    {
        $zipCode = $columnResolver->getColumnValue(ColumnResolver::COLUMN_ZIP, $rowData);
        if ($zipCode === '') {
            $zipCode = '*';
        }
        return $zipCode;
    }

    /**
     * @param array $rowData
     * @param int $rowNumber
     * @param string $conditionFullName
     * @param ColumnResolver $columnResolver
     * @return bool|float
     * @throws ColumnNotFoundException
     * @throws RowException
     */
    private function getConditionValue(array $rowData, $rowNumber, $conditionFullName, ColumnResolver $columnResolver)
    {
        // validate condition value
        $conditionValue = $columnResolver->getColumnValue($conditionFullName, $rowData);
        if ($conditionValue === '' || $conditionValue === '*') {
            $conditionValue = 0;
        }
        $value = $this->parseDecimalValue($conditionValue);
        if ($value === false) {
            throw new RowException(
                __(
                    'Please correct %1 "%2" in the Row #%3.',
                    $conditionFullName,
                    $conditionValue,
                    $rowNumber
                )
            );
        }
        return $value;
    }

    /**
     * @param array $rowData
     * @param int $rowNumber
     * @param ColumnResolver $columnResolver
     * @return bool|float
     * @throws ColumnNotFoundException
     * @throws RowException
     */
    private function getPrice(array $rowData, $rowNumber, ColumnResolver $columnResolver)
    {
        $priceValue = $columnResolver->getColumnValue(ColumnResolver::COLUMN_PRICE, $rowData);
        $price = $this->parseDecimalValue($priceValue);
        if ($price === false) {
            throw new RowException(__('Please correct Shipping Price "%1" in the Row #%2.', $priceValue, $rowNumber));
        }
        return $price;
    }

    /**
     * Parse and validate positive decimal value
     * Return false if value is not decimal or is not positive
     *
     * @param string $value
     * @return bool|float
     */
    private function parseDecimalValue($value)
    {
        $result = false;
        if (is_numeric($value)) {
            $value = (double)sprintf('%.4F', $value);
            if ($value >= 0.0000) {
                $result = $value;
            }
        }
        return $result;
    }
}
