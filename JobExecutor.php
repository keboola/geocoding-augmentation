<?php
/**
 * Created by IntelliJ IDEA.
 * User: JakubM
 * Date: 04.09.14
 * Time: 15:19
 */

namespace Keboola\GeocodingBundle;

use Geocoder\Geocoder;
use Geocoder\Provider\ChainProvider;
use Geocoder\Provider\GoogleMapsProvider;
use Geocoder\Provider\MapQuestProvider;
use Geocoder\Provider\NominatimProvider;
use Geocoder\Provider\YandexProvider;
use Keboola\GeocodingBundle\Service\EventLogger;
use Keboola\GeocodingBundle\Service\SharedStorage;
use Keboola\GeocodingBundle\Service\UserStorage;
use Keboola\GeocodingBundle\Geocoder\GuzzleAdapter;
use League\Geotools\Batch\Batch;
use League\Geotools\Exception\InvalidArgumentException;
use Monolog\Logger;
use Syrup\ComponentBundle\Exception\UserException;
use Syrup\ComponentBundle\Filesystem\Temp;
use Syrup\ComponentBundle\Job\Metadata\Job;

class JobExecutor extends \Syrup\ComponentBundle\Job\Executor
{
	/**
	 * @var \Syrup\ComponentBundle\Filesystem\Temp
	 */
	protected $temp;
	/**
	 * @var \Monolog\Logger
	 */
	protected $logger;
	/**
	 * @var UserStorage
	 */
	protected $userStorage;
	/**
	 * @var EventLogger
	 */
	protected $eventLogger;
	/**
	 * @var Batch
	 */
	protected $geotoolsBatch;
	/**
	 * @var SharedStorage
	 */
	protected $sharedStorage;


	public function __construct(SharedStorage $sharedStorage, Temp $temp, Logger $logger, $googleApiKey, $mapQuestKey)
	{
		$adapter = new GuzzleAdapter();
		$geocoder = new Geocoder();
		$geocoder->registerProvider(new ChainProvider(array(
			new GoogleMapsProvider($adapter, null, null, true, $googleApiKey),
			new MapQuestProvider($adapter, $mapQuestKey),
			new YandexProvider($adapter),
			new NominatimProvider($adapter, 'http://nominatim.openstreetmap.org'),
		)));
		$geotools = new \League\Geotools\Geotools();
		$this->geotoolsBatch = $geotools->batch($geocoder);

		$this->temp = $temp;
		$this->logger = $logger;
		$this->sharedStorage = $sharedStorage;
	}

	public function execute(Job $job)
	{
		$params = $job->getParams();
		$forwardGeocoding = $job->getCommand() == 'geocode';

		// Check required params
		if (!isset($params['tableId'])) {
			throw new UserException('Parameter tableId is required');
		}
		if ($forwardGeocoding &&!isset($params['location'])) {
			throw new UserException('Parameter location is required');
		}
		if (!$forwardGeocoding && !isset($params['latitude'])) {
			throw new UserException('Parameter latitude is required');
		}
		if (!$forwardGeocoding && !isset($params['longitude'])) {
			throw new UserException('Parameter longitude is required');
		}

		$this->eventLogger = new EventLogger($this->storageApi, $job->getId());
		$this->userStorage = new UserStorage($this->storageApi, $this->temp);

		// Download file with data column to disk and read line-by-line
		// Query Geocoding API by 50 queries
		$batchNumber = 1;
		$countInBatch = 50;
		$lines = array();
		$userTableParams = $forwardGeocoding? $params['location'] : array($params['latitude'], $params['longitude']);
		$dataFile = $this->userStorage->getData($params['tableId'], $userTableParams);
		$handle = fopen($dataFile, "r");
		if ($handle) {
			while (($line = fgetcsv($handle)) !== false) {
				$lines[] = $line;

				if (count($lines) >= $countInBatch) {
					$this->geocodeBatch($forwardGeocoding, $lines);

					$lines = array();
					$this->eventLogger->log(sprintf('Processed %d queries', $batchNumber * $countInBatch));
					$batchNumber++;
				}

			}
		}
		if (count($lines)) {
			$this->geocodeBatch($forwardGeocoding, $lines);
			$this->eventLogger->log(sprintf('Processed %d queries', $batchNumber * $countInBatch));
		}
		fclose($handle);

		$this->userStorage->uploadData();
	}


	public function geocodeBatch($forwardGeocoding, $lines)
	{
		$queries = array();
		$queriesToCheck = array();
		foreach ($lines as $line) {
			if ($forwardGeocoding) {
				$queries[] = $line[0];
				$queriesToCheck[] = $line[0];
			} else {
				$query = sprintf('%s %s', $line[0], $line[1]);
				// Basically analyze validity of coordinate
				if ($line[0] === null || $line[1] === null || !is_numeric($line[0]) || !is_numeric($line[1])) {
					$this->eventLogger->log(sprintf("Value '%s' is not valid coordinate", $query), array(), null, EventLogger::TYPE_WARN);
					$this->userStorage->save(true, array('query' => $query));
				} else {
					try {
						$queries[] = new \League\Geotools\Coordinate\Coordinate(array($line[0], $line[1]));
						$queriesToCheck[] = $query;
					} catch (InvalidArgumentException $e) {
						$this->eventLogger->log(sprintf("Value '%s' is not valid coordinate", $query), array(), null, EventLogger::TYPE_WARN);
						$this->userStorage->save(true, array('query' => $query));
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
				$this->userStorage->save($forwardGeocoding, $cache[$flatQuery]);
				if (($forwardGeocoding && !$cache[$flatQuery]['latitude'] && !$cache[$flatQuery]['longitude'])
					|| (!$forwardGeocoding && !$cache[$flatQuery]['country'])) {
					$this->eventLogger->log(sprintf("No result for location '%s' found", $flatQuery), array(), null, EventLogger::TYPE_WARN);
				}
			}
		}

		if (count($queriesToGeocode)) {
			// Query for the rest not in cache
			$geocoded = $forwardGeocoding
				? $this->geotoolsBatch->geocode($queriesToGeocode)->parallel()
				: $this->geotoolsBatch->reverse($queriesToGeocode)->parallel();

			foreach ($geocoded as $g) {
				/** @var \League\Geotools\Batch\BatchGeocoded $g */
				$data = $this->sharedStorage->prepareData($g);

				$this->sharedStorage->save($data);
				$this->userStorage->save($forwardGeocoding, $data);

				if ($forwardGeocoding ? !$g->getLatitude() && !$g->getLongitude() : !$g->getCountry()) {
					$this->eventLogger->log(sprintf("No result for location '%s' found", $g->getQuery()), array(), null, EventLogger::TYPE_WARN);
				}
			}
		}
	}

}