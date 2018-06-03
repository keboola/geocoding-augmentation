<?php
/**
 * @package geocoding-augmentation
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GeocodingAugmentation;

use Geocoder\Geocoder;
use Geocoder\Model\AdminLevel;
use Geocoder\Provider\BingMaps;
use Geocoder\Provider\GoogleMaps;
use Geocoder\Provider\GoogleMapsBusiness;
use Geocoder\Provider\MapQuest;
use Geocoder\Provider\OpenCage;
use Geocoder\Provider\OpenStreetMap;
use Geocoder\Provider\TomTom;
use Geocoder\Provider\Yandex;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use InvalidArgumentException;
use Ivory\HttpAdapter\Guzzle6HttpAdapter;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geotools;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Augmentation
{
    const METHOD_GEOCODE = 'geocode';
    const METHOD_REVERSE = 'reverse';


    /** @var Geocoder */
    protected $geocoder;
    /** @var Geotools  */
    protected $geotools;
    /** @var UserStorage */
    protected $userStorage;

    protected $provider;
    protected $locale;

    
    public function __construct($outputFile, $destination, $config)
    {
        $this->provider = $config['provider'];
        $this->locale = !empty($config['locale']) ? $config['locale'] : null;

        $httpAdapter = $this->initAdapter();
        $provider = $this->setupProvider($httpAdapter, $config);

        $this->geocoder = new \Geocoder\ProviderAggregator();
        $this->geocoder
            ->registerProvider($provider)
            ->using($config['provider']);
        $this->geotools = new Geotools();
        
        $this->userStorage = new UserStorage($outputFile, $destination);
    }

    public function process($method, $dataFile)
    {
        $csvFile = new \Keboola\Csv\CsvFile($dataFile);

        // query for each 50 lines from the file
        $countInBatch = 50;
        $queries = [];
        foreach ($csvFile as $row => $line) {
            if ($row == 0) {
                continue;
            }
            $queries[] = $line;

            // Run for every 50 lines
            if (count($queries) >= $countInBatch) {
                $this->processBatch($method, $queries);

                $queries = [];
            }
        }

        if (count($queries)) {
            // run the rest of lines above the highest multiple of 50
            $this->processBatch($method, $queries);
        }
    }


    public function processBatch($method, $queries)
    {
        $batch = $this->geotools->batch($this->geocoder);

        if ($method == self::METHOD_GEOCODE) {
            $queries = $this->prepareLocationsBatch($queries);
            $batch->geocode($queries);
        } else {
            $queries = $this->prepareCoordinatesBatch($queries);

            try {
                $batch->reverse($queries);
            } catch (InvalidArgumentException $e) {
                throw new Exception($e->getMessage());
            }
        }
        $result = $batch->parallel();

        foreach ($result as $g) {
            /** @var \League\Geotools\Batch\BatchGeocoded $g */
            $address = $g->getAddress();
            $adminLevels = $address ? $address->getAdminLevels() : null;
            /** @var AdminLevel $region */
            $region = $adminLevels && $adminLevels->has(1) ? $adminLevels->get(1) : null;
            /** @var AdminLevel $county */
            $county = $adminLevels && $adminLevels->has(2) ? $adminLevels->get(2) : null;
            $bounds = $address ? $address->getBounds() : null;
            $country = $address ? $address->getCountry() : null;
            $data = [
                'primary' => md5($g->getQuery().':'.$this->provider.':'.$this->locale),
                'query' => $g->getQuery(),
                'provider' => $this->provider,
                'locale' => $this->locale,
                'latitude' => $g->getLatitude(),
                'longitude' => $g->getLongitude(),
                'bounds_south' => $bounds ? $bounds->getSouth() : null,
                'bounds_east' => $bounds ? $bounds->getEast() : null,
                'bounds_west' => $bounds ? $bounds->getWest() : null,
                'bounds_north' => $bounds ? $bounds->getNorth() : null,
                'streetNumber' => $address ? $address->getStreetNumber() : null,
                'streetName' => $address ? $address->getStreetName() : null,
                'city' => $address ? $address->getLocality() : null,
                'zipcode' => $address ? $address->getPostalCode() : null,
                'cityDistrict' => $address ? $address->getSubLocality() : null,
                'county' => $county ? $county->getName() : null,
                'countyCode' => $county ? $county->getCode() : null,
                'region' => $region ? $region->getName() : null,
                'regionCode' => $region ? $region->getCode() : null,
                'country' => $country ? $country->getName() : null,
                'countryCode' => $country ? $country->getCode() : null,
                'timezone' => $address ? $address->getTimezone() : null,
                'exceptionMessage' => $g->getExceptionMessage()
            ];

            if (empty($data['exceptionMessage'])) {
                if ($method == self::METHOD_GEOCODE
                    ? $g->getLatitude() == 0 && $g->getLongitude() == 0
                    : !$g->getAddress()->getCountry()
                ) {
                    error_log("No result for location '{$g->getQuery()}' found");
                }
            } else {
                error_log("API error for location '{$g->getQuery()}': {$data['exceptionMessage']}");
                if (strpos($data['exceptionMessage'], 'quota exceeded') !== false) {
                    error_log("API quota exceeded!");
                }
            }
            $this->userStorage->save($data);
        }
    }

    protected function prepareCoordinatesBatch($queries)
    {
        $result = [];
        foreach ($queries as $q) {
            if ($q[0] === null || $q[1] === null || (!$q[0] && !$q[1]) || !is_numeric($q[0]) || !is_numeric($q[1])) {
                error_log("Value '{$q[0]} {$q[1]}' is not valid coordinate");
            } else {
                try {
                    $result[] = new Coordinate([$q[0], $q[1]]);
                } catch (InvalidArgumentException $e) {
                    error_log("Value '{$q[0]} {$q[1]}' is not valid coordinate: ".$e->getMessage());
                }
            }
        }
        return $result;
    }

    protected function prepareLocationsBatch($queries)
    {
        $result = [];
        foreach ($queries as $q) {
            $result[] = $q[0];
        }
        return $result;
    }

    protected function initAdapter()
    {
        $handlerStack = HandlerStack::create();
        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                return (!$response || $response->getStatusCode() > 499) && $retries <= 10;
            },
            function ($retries) {
                return (int) pow(2, $retries - 1) * 1000;
            }
        ));
        $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);
        return new Guzzle6HttpAdapter($client);
    }

    protected function setupProvider($httpAdapter, $config)
    {
        $locale = isset($config['locale']) ? $config['locale'] : 'en';
        if (!isset($config['provider'])) {
            throw new Exception("No provider configured");
        }

        if (in_array($config['provider'], ['google_maps', 'bing_maps', 'map_quest', 'tomtom', 'opencage'])) {
            if (!isset($config['apiKey'])) {
                throw new Exception("Provider {$config['provider']} needs 'apiKey' attribute configured");
            }
        }
        switch ($config['provider']) {
            case 'google_maps':
                return new GoogleMaps($httpAdapter, $locale, null, true, $config['apiKey']);
                break;
            case 'google_maps_business':
                if (!isset($config['clientId'])) {
                    throw new Exception("Provider {$config['provider']} needs 'clientId' attribute configured");
                }
                if (!isset($config['privateKey'])) {
                    throw new Exception("Provider {$config['provider']} needs 'privateKey' attribute configured");
                }
                return new GoogleMapsBusiness(
                    $httpAdapter,
                    $config['clientId'],
                    $config['privateKey'],
                    $locale,
                    null,
                    true
                );
                break;
            case 'bing_maps':
                return new BingMaps($httpAdapter, $config['apiKey'], $locale);
                break;
            case 'yandex':
                return new Yandex($httpAdapter, $locale);
                break;
            case 'map_quest':
                return new MapQuest($httpAdapter, $config['apiKey'], $locale);
                break;
            case 'tomtom':
                return new TomTom($httpAdapter, $config['apiKey'], $locale);
                break;
            case 'opencage':
                return new OpenCage($httpAdapter, $config['apiKey'], true, $locale);
                break;
            case 'openstreetmap':
                return new OpenStreetMap($httpAdapter, $locale);
                break;
            default:
                throw new Exception("Unknown configured provider {$config['provider']}");
        }
    }
}
