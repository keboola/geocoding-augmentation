<?php
namespace Keboola\GeocodingAugmentation;

use Geocoder\Geocoder;
use Geocoder\Model\AdminLevel;
use Geocoder\Provider\BingMaps\BingMaps;
use Geocoder\Provider\GoogleMaps\GoogleMaps;
use Geocoder\Provider\MapQuest\MapQuest;
use Geocoder\Provider\Nominatim\Nominatim;
use Geocoder\Provider\OpenCage\OpenCage;
use Geocoder\Provider\TomTom\TomTom;
use Geocoder\Provider\Yandex\Yandex;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Http\Adapter\Guzzle6\Client;
use InvalidArgumentException;
use Keboola\Csv\CsvReader;
use League\Geotools\Batch\BatchGeocoded;
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
        $this->locale = !empty($config['locale']) ? $config['locale'] : 'en';

        $httpClient = $this->initHttpClient();
        $provider = $this->setupProvider($httpClient, $config);

        $this->geocoder = new ProviderAggregator($provider, $this->locale);
        $this->geotools = new Geotools();

        $this->userStorage = new UserStorage($outputFile, $destination);
    }

    public function process($method, $dataFile)
    {
        $csvFile = new CsvReader($dataFile);

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
            $batch->reverse($queries);
        }
        $result = $batch->parallel();

        foreach ($result as $g) {
            /** @var BatchGeocoded $g */
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
                if ($this->isNoResultError($method, $g)) {
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

    private function isNoResultError($method, $g)
    {
        if ($method == self::METHOD_GEOCODE) {
            return $g->getLatitude() == 0 && $g->getLongitude() == 0;
        } else {
            return (is_null($g->getAddress()) || !$g->getAddress()->getCountry());
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

    protected function initHttpClient()
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
        return new Client($client);
    }

    protected function setupProvider($httpClient, $config)
    {
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
                return new GoogleMaps($httpClient, null, $config['apiKey']);
                break;
            case 'google_maps_business':
                if (!isset($config['clientId'])) {
                    throw new Exception("Provider {$config['provider']} needs 'clientId' attribute configured");
                }
                if (!isset($config['privateKey'])) {
                    throw new Exception("Provider {$config['provider']} needs 'privateKey' attribute configured");
                }
                return GoogleMaps::business($httpClient, $config['clientId'], $config['privateKey']);
                break;
            case 'bing_maps':
                return new BingMaps($httpClient, $config['apiKey']);
                break;
            case 'yandex':
                return new Yandex($httpClient);
                break;
            case 'map_quest':
                return new MapQuest($httpClient, $config['apiKey']);
                break;
            case 'tomtom':
                return new TomTom($httpClient, $config['apiKey']);
                break;
            case 'opencage':
                return new OpenCage($httpClient, $config['apiKey']);
                break;
            case 'nominatim':
                return new Nominatim(
                    $httpClient,
                    'https://nominatim.openstreetmap.org',
                    'keboola/geocoding-augmentation'
                );
                break;
            default:
                throw new Exception("Unknown configured provider {$config['provider']}");
        }
    }
}
