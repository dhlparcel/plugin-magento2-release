<?php

namespace DHLParcel\Shipping\Model\Service;

use DHLParcel\Shipping\Model\Api\Connector;
use DHLParcel\Shipping\Model\Data\Api\Response\ServicePoint as ServicePointResponse;
use DHLParcel\Shipping\Model\Data\Api\Response\ServicePointFactory as ServicePointResponseFactory;

class ServicePoint
{
    protected $connector;
    protected $servicePointResponseFactory;

    public function __construct(
        Connector $connector,
        ServicePointResponseFactory $servicePointResponseFactory
    ) {
        $this->connector = $connector;
        $this->servicePointResponseFactory = $servicePointResponseFactory;
    }

    /**
     * @param $postalcode
     * @param $country
     * @param int $limit
     * @return ServicePointResponse[]
     */
    public function search($postalcode, $country, $limit = 13)
    {
        $servicePointsResponse = $this->connector->get('parcel-shop-locations/' . $country, [
            'limit'       => $limit,
            'zipCode'     => strtoupper($postalcode),
            'serviceType' => 'parcel-last-mile',
        ]);

        if (!$servicePointsResponse || !is_array($servicePointsResponse)) {
            return [];
        }

        $servicePoints = [];
        foreach ($servicePointsResponse as $servicePointResponse) {
            /** @var ServicePointResponse $servicePointResponse */
            $servicePoint = $this->servicePointResponseFactory->create(['automap' => $servicePointResponse]);
            $servicePoint->country = $country;
            if ($servicePoint->shopType === 'packStation' && empty($servicePoint->name)) {
                $servicePoint->name = $servicePoint->keyword;
            }
            $servicePoints[] = $servicePoint;
        }

        return $servicePoints;
    }

    /**
     * @param $postalcode
     * @param $country
     * @return bool|ServicePointResponse
     */
    public function getClosest($postalcode, $country)
    {
        $servicePoints = $this->search($postalcode, $country, 13);

        if (!is_array($servicePoints)) {
            return false;
        }

        foreach ($servicePoints as $servicePoint) {
            if ($servicePoint->shopType !== 'packStation' || $servicePoint->country !== 'DE') {
                return $servicePoint;
            }
        }
        return false;
    }

    /**
     * @param $id
     * @param $country
     * @return ServicePointResponse|null
     * isn't actualy being used right now
     */
    public function get($id, $country)
    {
        if (($position = strpos($id, "|")) !== false) {
            $post_number = substr($id, $position + 1);
        } else {
            $post_number = null;
        }
        // Remove any additional fields
        $id = strstr($id, '|', true) ?: $id;

        $servicePointResponse = $this->connector->get(sprintf('parcel-shop-locations/%s/%s', $country, $id));
        if (!$servicePointResponse) {
            return null;
        }

        /** @var ServicePointResponse $servicePointResponse */
        $servicePoint = $this->servicePointResponseFactory->create(['automap' => $servicePointResponse]);
        $servicePoint->country = $country;
        if ($servicePoint->shopType === 'packStation') {
            if (empty($servicePoint->name)) {
                $servicePoint->name = $servicePoint->keyword;
            }
            if (!empty($post_number)) {
                $servicePoint->name = $servicePoint->name . ' ' . $post_number;
                $servicePoint->id = $servicePoint->id . '|' . $post_number;
            }
        }
        return $servicePoint;
    }
}
