<?php
/**
 * @package geocoding-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use League\Geotools\Batch\BatchGeocoded;

class SharedStorage
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;
    const TABLE_NAME = 'geocoding_cache';

    public function __construct(\Doctrine\DBAL\Connection $db)
    {
        $this->db = $db;
    }

    public function get($queries)
    {
        $result = array();
        $query = $this->db->fetchAll(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE query IN (?)',
            array($queries),
            array(Connection::PARAM_STR_ARRAY)
        );
        foreach ($query as $q) {
            $result[$q['query']] = $q;
        }
        return $result;
    }

    public function save($data)
    {
        try {
            $this->db->insert(self::TABLE_NAME, $data);
        } catch (DBALException $e) {
            // Ignore
        }
    }

    public function prepareData(BatchGeocoded $batch)
    {
        $bounds = $batch->getBounds();
        $data = array(
            'query' => $batch->getQuery(),
            'providerName' => $batch->getProviderName(),
            'latitude' => $batch->getLatitude(),
            'longitude' => $batch->getLongitude(),
            'bounds_south' =>  $bounds['south'],
            'bounds_east' => $bounds['east'],
            'bounds_west' => $bounds['west'],
            'bounds_north' => $bounds['north'],
            'streetNumber' => $batch->getStreetNumber(),
            'streetName' => $batch->getStreetName(),
            'city' => $batch->getCity(),
            'zipcode' => $batch->getZipcode(),
            'cityDistrict' => $batch->getCityDistrict(),
            'county' => $batch->getCounty(),
            'countyCode' => $batch->getCountyCode(),
            'region' => $batch->getRegion(),
            'regionCode' => $batch->getRegionCode(),
            'country' => $batch->getCountry(),
            'countryCode' => $batch->getCountryCode(),
            'timezone' => $batch->getTimezone(),
            'exceptionMessage' => $batch->getExceptionMessage()
        );
        return $data;
    }
}
