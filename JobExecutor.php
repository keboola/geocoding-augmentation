<?php
/**
 * @package geocoding-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation;

use Geocoder\Geocoder;
use Geocoder\Provider\GoogleMapsProvider;
use Geocoder\Provider\MapQuestProvider;
use Geocoder\Provider\NominatimProvider;
use Geocoder\Provider\YandexProvider;
use Keboola\GeocodingAugmentation\Service\ConfigurationStorage;
use Keboola\GeocodingAugmentation\Service\EventLogger;
use Keboola\GeocodingAugmentation\Service\SharedStorage;
use Keboola\GeocodingAugmentation\Service\UserStorage;
use Keboola\GeocodingAugmentation\Geocoder\GuzzleAdapter;
use Keboola\GeocodingAugmentation\Geocoder\ChainProvider;
use League\Geotools\Exception\InvalidArgumentException;
use League\Geotools\Geotools;
use Keboola\Temp\Temp;
use Keboola\Syrup\Job\Metadata\Job;

class JobExecutor extends \Keboola\Syrup\Job\Executor
{
    /**
     * @var \Keboola\Temp\Temp
     */
    protected $temp;
    /**
     * @var UserStorage
     */
    protected $userStorage;
    /**
     * @var EventLogger
     */
    protected $eventLogger;
    /**
     * @var Geotools
     */
    protected $geotools;
    /**
     * @var Geocoder
     */
    protected $geocoder;
    /**
     * @var SharedStorage
     */
    protected $sharedStorage;
    /**
     * @var ChainProvider
     */
    protected $chainProvider;

    public function __construct(SharedStorage $sharedStorage, Temp $temp, $googleApiKey, $mapQuestKey)
    {
        $adapter = new GuzzleAdapter();
        $this->geocoder = new Geocoder();
        $this->chainProvider = new ChainProvider(array(
            new GoogleMapsProvider($adapter, null, null, true, $googleApiKey),
            new MapQuestProvider($adapter, $mapQuestKey),
            new YandexProvider($adapter),
            new NominatimProvider($adapter, 'http://nominatim.openstreetmap.org'),
        ));
        $this->geocoder->registerProvider($this->chainProvider);
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

            foreach ($configuration['tables'] as $configTable) {
                $userTableParams = $forwardGeocoding? $configTable['addressCol'] : array($configTable['latitudeCol'], $configTable['longitudeCol']);
                $dataFile = $this->userStorage->getData($configTable['tableId'], $userTableParams);

                $this->geocode($configId, $forwardGeocoding, $dataFile);
            }
        }

        $this->userStorage->uploadData();
    }

    public function geocode($configId, $forwardGeocoding, $dataFile)
    {
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
                    $this->geocodeBatch($configId, $forwardGeocoding, $lines);
                    $this->eventLogger->log(sprintf('Processed %d queries', $batchNumber * $countInBatch));

                    $lines = array();
                    $batchNumber++;
                }

            }
        }
        if (count($lines)) {
            // Run the rest of lines above the highest multiple of 50
            $this->geocodeBatch($configId, $forwardGeocoding, $lines);
            $this->eventLogger->log(sprintf('Processed %d queries', (($batchNumber - 1) * $countInBatch) + count($lines)));
        }
        fclose($handle);
    }


    public function geocodeBatch($configId, $forwardGeocoding, $lines)
    {
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
                    $this->userStorage->save($configId, array('query' => $query));
                } else {
                    try {
                        $queries[] = new \League\Geotools\Coordinate\Coordinate(array($line[0], $line[1]));
                        $queriesToCheck[] = $query;
                    } catch (InvalidArgumentException $e) {
                        $this->eventLogger->log(sprintf("Value '%s' is not valid coordinate", $query), array(), null, EventLogger::TYPE_WARN);
                        $this->userStorage->save($configId, array('query' => $query));
                    }
                }
            }
        }

        $cache = $this->sharedStorage->get($queriesToCheck);

        // Get from cache
        $queriesToGeocode = array();
        foreach ($queries as $query) {
            $flatQuery = is_object($query)? sprintf('%s, %s', $query->getLatitude(), $query->getLongitude()) : $query;
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
            $providersLog = $this->chainProvider->getProvidersLog();
            foreach ($result as $g) {
                /** @var \League\Geotools\Batch\BatchGeocoded $g */
                if (isset($providersLog[$g->getQuery()])) {
                    $g->setProviderName($providersLog[$g->getQuery()]);
                }
                $data = $this->sharedStorage->prepareData($g);

                $this->sharedStorage->save($data);
                $this->userStorage->save($configId, $data);

                if ($forwardGeocoding ? $g->getLatitude() == 0 && $g->getLongitude() == 0 : !$g->getCountry()) {
                    $this->eventLogger->log(sprintf("No result for location '%s' found", $g->getQuery()), array(), null, EventLogger::TYPE_WARN);
                }
            }
        }
    }
}
