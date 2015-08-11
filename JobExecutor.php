<?php
/**
 * @package geocoding-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation;

use Geocoder\Geocoder;
use Geocoder\Provider\BingMapsProvider;
use Geocoder\Provider\GoogleMapsBusinessProvider;
use Geocoder\Provider\GoogleMapsProvider;
use Geocoder\Provider\MapQuestProvider;
use Geocoder\Provider\NominatimProvider;
use Geocoder\Provider\OpenCageProvider;
use Geocoder\Provider\OpenStreetMapProvider;
use Geocoder\Provider\ProviderInterface;
use Geocoder\Provider\TomTomProvider;
use Geocoder\Provider\YandexProvider;
use Keboola\GeocodingAugmentation\Service\ConfigurationStorage;
use Keboola\GeocodingAugmentation\Service\EventLogger;
use Keboola\GeocodingAugmentation\Service\SharedStorage;
use Keboola\GeocodingAugmentation\Service\UserStorage;
use Keboola\GeocodingAugmentation\Geocoder\GuzzleAdapter;
use Keboola\GeocodingAugmentation\Geocoder\ChainProvider;
use Keboola\Syrup\Exception\UserException;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Exception\InvalidArgumentException;
use League\Geotools\Geotools;
use Keboola\Temp\Temp;
use Keboola\Syrup\Job\Metadata\Job;

class JobExecutor extends \Keboola\Syrup\Job\Executor
{
    /** @var \Keboola\Temp\Temp */
    protected $temp;
    /** @var UserStorage */
    protected $userStorage;
    /** @var EventLogger */
    protected $eventLogger;
    /** @var Geotools */
    protected $geotools;
    /** @var Geocoder */
    protected $geocoder;
    /** @var SharedStorage */
    protected $sharedStorage;
    /** @var ProviderInterface */
    protected $provider;
    protected $defaultGoogleKey;

    public function __construct(SharedStorage $sharedStorage, Temp $temp, $googleApiKey)
    {
        $this->defaultGoogleKey = $googleApiKey;
        $this->geocoder = new Geocoder();
        $this->geotools = new Geotools();

        $this->temp = $temp;
        $this->sharedStorage = $sharedStorage;
    }

    public function execute(Job $job)
    {
        $configurationStorage = new ConfigurationStorage($this->storageApi);
        $this->eventLogger = new EventLogger($this->storageApi, $job->getId());
        $this->userStorage = new UserStorage($this->storageApi, $this->temp);

        $params = $job->getParams();
        $configIds = isset($params['config'])? array($params['config']) : $configurationStorage->getConfigurationsList();

        foreach ($configIds as $configId) {
            $configuration = $configurationStorage->getConfiguration($configId);
            $forwardGeocoding = $configuration['method'] == ConfigurationStorage::METHOD_GEOCODE;
            $providerConfig = array_diff_key($configuration, ['method' => '', 'tables' => '']);

            foreach ($configuration['tables'] as $configTable) {
                $userTableParams = $forwardGeocoding? $configTable['addressCol'] : array($configTable['latitudeCol'], $configTable['longitudeCol']);
                $dataFile = $this->userStorage->getData($configTable['tableId'], $userTableParams);

                $this->geocode($configId, $forwardGeocoding, $providerConfig, $dataFile);
            }
        }

        $this->userStorage->uploadData();
    }

    public function geocode($configId, $forwardGeocoding, $providerConfig, $dataFile)
    {
        $this->setupProvider($providerConfig);

        // Download file with data column to disk and read line-by-line
        // Query Geocoding API by 50 queries
        $batchNumber = 1;
        $countInBatch = 50;
        $lines = array();
        $handle = fopen($dataFile, "r");
        if ($handle) {
            while (($line = fgetcsv($handle)) !== false) {
                $lines[] = $line;

                // Run geocoding every 50 lines
                if (count($lines) >= $countInBatch) {
                    $this->geocodeBatch($configId, $forwardGeocoding, $lines, $providerConfig);
                    $this->eventLogger->log(sprintf('Processed %d queries', $batchNumber * $countInBatch));

                    $lines = array();
                    $batchNumber++;
                }

            }
        }
        if (count($lines)) {
            // Run the rest of lines above the highest multiple of 50
            $this->geocodeBatch($configId, $forwardGeocoding, $lines, $providerConfig);
            $this->eventLogger->log(sprintf('Processed %d queries', (($batchNumber - 1) * $countInBatch) + count($lines)));
        }
        fclose($handle);
    }


    public function geocodeBatch($configId, $forwardGeocoding, $lines, $providerConfig)
    {
        $provider = isset($providerConfig['provider']) ? $providerConfig['provider'] : null;
        $locale = isset($providerConfig['locale']) ? $providerConfig['locale'] : null;

        $queries = array();
        $queriesToCheck = array();
        foreach ($lines as $line) {
            if ($forwardGeocoding) {
                $queries[] = $line[0];
                $queriesToCheck[] = $line[0];
            } else {
                $query = sprintf('%s, %s', (float)$line[0], (float)$line[1]);
                // Basically analyze validity of coordinate
                if ($line[0] === null || $line[1] === null || !is_numeric($line[0]) || !is_numeric($line[1])) {
                    $this->eventLogger->log(sprintf("Value '%s' is not valid coordinate", $query), array(), null, EventLogger::TYPE_WARN);
                    $this->userStorage->save($configId, array('query' => $query, 'provider' => $provider, 'locale' => $locale));
                } else {
                    try {
                        $queries[] = new Coordinate(array($line[0], $line[1]));
                        $queriesToCheck[] = $query;
                    } catch (InvalidArgumentException $e) {
                        $this->eventLogger->log(sprintf("Value '%s' is not valid coordinate", $query), array(), null, EventLogger::TYPE_WARN);
                        $this->userStorage->save($configId, array('query' => $query, 'provider' => $provider, 'locale' => $locale));
                    }
                }
            }
        }

        // Get from cache
        $cache = $this->sharedStorage->get($queriesToCheck, $provider, $locale);
        $queriesToGeocode = array();
        foreach ($queries as $query) {
            $flatQuery = ($query instanceof Coordinate) ? sprintf('%s, %s', $query->getLatitude(), $query->getLongitude()) : $query;
            if (!isset($cache[$flatQuery])) {
                $queriesToGeocode[] = $query;
            } else {
                $this->userStorage->save($configId, $cache[$flatQuery]);
                if (($forwardGeocoding && $cache[$flatQuery]['latitude'] == 0 && $cache[$flatQuery]['longitude'] == 0)
                    || (!$forwardGeocoding && !$cache[$flatQuery]['country'])) {
                    $this->eventLogger->log(sprintf("No result for location '%s' found", $flatQuery), array(), null, EventLogger::TYPE_WARN);
                }
            }
        }

        if (count($queriesToGeocode)) {
            // Query for the rest not in cache
            $batch = $this->geotools->batch($this->geocoder);

            if ($forwardGeocoding) {
                $batch->geocode($queriesToGeocode);
            } else {
                $batch->reverse($queriesToGeocode);
            }

            $result = $batch->parallel();
            foreach ($result as $g) {
                /** @var \League\Geotools\Batch\BatchGeocoded $g */
                $data = $this->sharedStorage->prepareData($g, $provider, $locale);

                $this->sharedStorage->save($data);
                $this->userStorage->save($configId, $data);

                if ($forwardGeocoding ? $g->getLatitude() == 0 && $g->getLongitude() == 0 : !$g->getCountry()) {
                    $this->eventLogger->log(sprintf("No result for location '%s' found", $g->getQuery()), array(), null, EventLogger::TYPE_WARN);
                }
            }

        }
    }
    
    public function setupProvider($config)
    {
        $httpAdapter = new GuzzleAdapter();
        $locale = isset($config['locale']) ? $config['locale'] : 'en';
        if (isset($config['provider'])) {
            if (in_array($config['provider'], ['google_maps', 'bing_maps', 'map_quest', 'tomtom', 'opencage'])) {
                if (!isset($config['apiKey'])) {
                    throw new UserException("Provider {$config['provider']} needs 'apiKey' attribute configured");
                }
            }
            switch ($config['provider']) {
                case 'google_maps':
                    $this->provider = new GoogleMapsProvider($httpAdapter, $locale, null, true, $config['apiKey']);
                    break;
                case 'google_maps_business':
                    if (!isset($config['clientId'])) {
                        throw new UserException("Provider {$config['provider']} needs 'clientId' attribute configured");
                    }
                    if (!isset($config['privateKey'])) {
                        throw new UserException("Provider {$config['provider']} needs 'privateKey' attribute configured");
                    }
                    $this->provider = new GoogleMapsBusinessProvider($httpAdapter, $config['clientId'], $config['privateKey'], $locale, null, true);
                    break;
                case 'bing_maps':
                    $this->provider = new BingMapsProvider($httpAdapter, $config['apiKey'], $locale);
                    break;
                case 'yandex':
                    $this->provider = new YandexProvider($httpAdapter, $locale);
                    break;
                case 'map_quest':
                    $this->provider = new MapQuestProvider($httpAdapter, $config['apiKey'], $locale);
                    break;
                case 'tomtom':
                    $this->provider = new TomTomProvider($httpAdapter, $config['apiKey'], $locale);
                    break;
                case 'opencage':
                    $this->provider = new OpenCageProvider($httpAdapter, $config['apiKey'], true, $locale);
                    break;
                case 'openstreetmap':
                    $this->provider = new OpenStreetMapProvider($httpAdapter, $locale);
                    break;
                default:
                    throw new UserException("Unknown configured provider {$config['provider']}");
            }
        }

        // @TODO Fallback to default
        if (!$this->provider) {
            $this->provider = new ChainProvider(array(
                new GoogleMapsProvider($httpAdapter, $locale, null, true, $this->defaultGoogleKey),
                new YandexProvider($httpAdapter, $locale),
                new NominatimProvider($httpAdapter, 'http://nominatim.openstreetmap.org', $locale),
            ));
        }

        $this->geocoder->registerProvider($this->provider);
    }
}
