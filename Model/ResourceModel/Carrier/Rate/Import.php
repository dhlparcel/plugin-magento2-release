<?php

namespace DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\File\ReadInterface;
use DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\CSV\ColumnResolver;
use DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\CSV\ColumnResolverFactory;
use DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\CSV\RowException;
use DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\CSV\RowParser;

class Import
{
    /**
     * @var array
     */
    private $errors = [];
    /**
     * @var CSV\RowParser
     */
    private $rowParser;
    /**
     * @var ColumnResolverFactory
     */
    private $columnResolverFactory;
    /**
     * @var DataHashGenerator
     */
    private $dataHashGenerator;
    /**
     * @var string
     */
    private $delimiter;

    public function __construct(
        RowParser $rowParser,
        ColumnResolverFactory $columnResolverFactory,
        DataHashGenerator $dataHashGenerator
    ) {
        $this->rowParser = $rowParser;
        $this->columnResolverFactory = $columnResolverFactory;
        $this->dataHashGenerator = $dataHashGenerator;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return (bool)count($this->getErrors());
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->rowParser->getColumns();
    }

    /**
     * @param ReadInterface $file
     * @param $websiteId
     * @param $conditionShortName
     * @param $conditionFullName
     * @param $shippingMethod
     * @param int $bunchSize
     * @return \Generator
     * @throws CSV\ColumnNotFoundException
     * @throws LocalizedException
     */
    public function getData(ReadInterface $file, $websiteId, $conditionShortName, $conditionFullName, $shippingMethod, $bunchSize = 5000)
    {
        $this->errors = [];
        $this->delimiter = ',';
        $headers = $this->getHeaders($file);
        /** @var ColumnResolver $columnResolver */
        $columnResolver = $this->columnResolverFactory->create(['headers' => $headers]);

        $rowNumber = 1;
        $items = [];
        $uniqueHash = [];
        while (false !== ($csvLine = $file->readCsv(0, $this->delimiter))) {
            try {
                $rowNumber++;
                if (empty($csvLine) || count($csvLine) === 1 && empty($csvLine[0])) {
                    // Skip when line is completely empty and when extra newlines are added on end of file
                    continue;
                }
                $rowData = $this->rowParser->parse(
                    $csvLine,
                    $rowNumber,
                    $websiteId,
                    $shippingMethod,
                    $conditionShortName,
                    $conditionFullName,
                    $columnResolver
                );

                // protect from duplicate
                $hash = $this->dataHashGenerator->getHash($rowData);
                if (array_key_exists($hash, $uniqueHash)) {
                    throw new RowException(
                        __(
                            'Duplicate Row #%1 (duplicates row #%2)',
                            $rowNumber,
                            $uniqueHash[$hash]
                        )
                    );
                }
                $uniqueHash[$hash] = $rowNumber;

                $items[] = $rowData;
                if (count($items) === $bunchSize) {
                    yield $items;
                    $items = [];
                }
            } catch (RowException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        if (count($items)) {
            yield $items;
        }
    }

    /**
     * @param ReadInterface $file
     * @return array|bool
     * @throws LocalizedException
     */
    private function getHeaders(ReadInterface $file)
    {
        // check and skip headers
        $headers = $file->readCsv(0, $this->delimiter);
        if ($headers === false || count($headers) < 5) {
            $this->delimiter = ';';
            $file->seek(0);
            $headers = $file->readCsv(0, $this->delimiter);
            if ($headers === false || count($headers) < 5) {
                throw new LocalizedException(__('Please correct Table Rates File Format.'));
            }
        }
        return $headers;
    }
}
