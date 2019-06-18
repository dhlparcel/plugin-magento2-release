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
            'limit'   => $limit,
            'zipCode' => strtoupper($postalcode),
        ]);

        if (!$servicePointsResponse || !is_array($servicePointsResponse)) {
            return [];
        }

        $servicePoints = [];
        foreach ($servicePointsResponse as $servicePointResponse) {
            $servicePointResponse['country'] = $country;
            $servicePoints[] = $this->servicePointResponseFactory->create(['automap' => $servicePointResponse]);
        }

        return $servicePoints;
    }

    /**
     * @param $id
     * @param $country
     * @return ServicePointResponse|null
     */
    public function get($id, $country)
    {
        $servicePointResponse = $this->connector->get(sprintf('parcel-shop-locations/%s/%s', $country, $id));
        if (!$servicePointResponse) {
            return null;
        }

        $servicePointResponse['country'] = $country;
        return $this->servicePointResponseFactory->create(['automap' => $servicePointResponse]);
    }
}
