<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate;

class RateQuery
{
    /**
     * @var \Magento\Quote\Model\Quote\Address\RateRequest
     */
    private $request;

    /**
     * RateQuery constructor.
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     */
    public function __construct(
        \Magento\Quote\Model\Quote\Address\RateRequest $request
    ) {
        $this->request = $request;
    }

    /**
     * @param \Magento\Framework\DB\Select $select
     * @return \Magento\Framework\DB\Select
     */
    public function preparePreSelect(\Magento\Framework\DB\Select $select)
    {
        $select->where('website_id = :website_id AND store_id = :store_id')
            ->where('method_name = :method_name');

        // Render condition by condition name
        if (is_array($this->request->getConditionName())) {
            $orWhere = [];
            foreach (range(0, count($this->request->getConditionName())) as $conditionNumber) {
                $bindNameKey = sprintf(':condition_name_%d', $conditionNumber);
                $orWhere[] = "(condition_name = {$bindNameKey})";
            }

            if ($orWhere) {
                $select->where(implode(' OR ', $orWhere));
            }
        } else {
            $select->where('condition_name = :condition_name');
        }

        return $select;
    }

    /**
     * @param $method
     * @param $websiteId
     * @param $storeId
     * @return array
     */
    public function getPreBindings($method, $websiteId, $storeId)
    {
        $bind = [
            ':website_id' => (int)$websiteId,
            ':store_id' => (int)$storeId,
            ':method_name' => $method
        ];

        // Render condition by condition name
        if (is_array($this->request->getConditionName())) {
            $i = 0;
            foreach ($this->request->getConditionName() as $conditionName) {
                $bindNameKey = sprintf(':condition_name_%d', $i);
                $bind[$bindNameKey] = $conditionName;
                $i++;
            }
        } else {
            $bind[':condition_name'] = $this->request->getConditionName();
        }

        return $bind;
    }

    /**
     * @param \Magento\Framework\DB\Select $select
     * @return \Magento\Framework\DB\Select
     */
    public function prepareSelect(\Magento\Framework\DB\Select $select)
    {
        $select->where('website_id = :website_id AND store_id = :store_id')
            ->where('method_name = :method_name')
            ->order(['dest_country_id DESC', 'dest_region_id DESC', 'dest_zip DESC', 'condition_value DESC'])
            ->limit(1);

        $conditions = [
            "dest_country_id = :country_id AND dest_region_id = :region_id AND dest_zip = :postcode",
            "dest_country_id = :country_id AND dest_region_id = :region_id AND dest_zip = ''",

            // Handle wildcard
            "(dest_country_id = :country_id OR dest_country_id = 0 OR dest_country_id = '*') AND 
            (dest_region_id = :region_id OR dest_region_id = 0 OR dest_region_id = '*') AND 
            (
                (dest_zip != '*') AND 
                IF( 
                    RIGHT(dest_zip, 1) = '*', 
                    ((SUBSTR(:postcode, 1, (LENGTH(dest_zip)-1))) = LEFT(`dest_zip`, LENGTH(dest_zip)-1)), 
                    false 
                )
            )",

            // Handle asterisk in dest_zip field
            "dest_country_id = :country_id AND dest_region_id = :region_id AND dest_zip = '*'",
            "dest_country_id = :country_id AND dest_region_id = 0 AND dest_zip = '*'",
            "dest_country_id = '0' AND dest_region_id = :region_id AND dest_zip = '*'",
            "dest_country_id = '0' AND dest_region_id = 0 AND dest_zip = '*'",
            "dest_country_id = :country_id AND dest_region_id = 0 AND dest_zip = ''",
            "dest_country_id = :country_id AND dest_region_id = 0 AND dest_zip = :postcode",
            "dest_country_id = :country_id AND dest_region_id = 0 AND dest_zip = '*'"
        ];
        // Render destination condition
        $orWhere = '(' . implode(') OR (', $conditions) . ')';
        $select->where($orWhere);

        // Render condition by condition name
        if (is_array($this->request->getConditionName())) {
            $orWhere = [];
            foreach (range(0, count($this->request->getConditionName())) as $conditionNumber) {
                $bindNameKey = sprintf(':condition_name_%d', $conditionNumber);
                $bindValueKey = sprintf(':condition_value_%d', $conditionNumber);
                $orWhere[] = "(condition_name = {$bindNameKey} AND condition_value <= {$bindValueKey})";
            }

            if ($orWhere) {
                $select->where(implode(' OR ', $orWhere));
            }
        } else {
            $select->where('condition_name = :condition_name');
            $select->where('condition_value <= :condition_value');
        }

        return $select;
    }

    /**
     * @param $method
     * @param $websiteId
     * @param $storeId
     * @return array
     */
    public function getBindings($method, $websiteId, $storeId)
    {
        $bind = [
            ':website_id' => (int)$websiteId,
            ':store_id' => (int)$storeId,
            ':method_name' => $method,
            ':country_id' => $this->request->getDestCountryId(),
            ':region_id' => (int)$this->request->getDestRegionId(),
            ':postcode' => $this->request->getDestPostcode(),
        ];

        // Render condition by condition name
        if (is_array($this->request->getConditionName())) {
            $i = 0;
            foreach ($this->request->getConditionName() as $conditionName) {
                $bindNameKey = sprintf(':condition_name_%d', $i);
                $bindValueKey = sprintf(':condition_value_%d', $i);
                $bind[$bindNameKey] = $conditionName;
                $bind[$bindValueKey] = $this->request->getData($conditionName);
                $i++;
            }
        } else {
            $bind[':condition_name'] = $this->request->getConditionName();
            $bind[':condition_value'] = $this->request->getData($this->request->getConditionName());
        }

        return $bind;
    }

    /**
     * @return \Magento\Quote\Model\Quote\Address\RateRequest
     */
    public function getRequest()
    {
        return $this->request;
    }
}
