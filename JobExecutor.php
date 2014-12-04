<?php
/**
 * Created by IntelliJ IDEA.
 * User: JakubM
 * Date: 04.09.14
 * Time: 15:19
 */

namespace Keboola\GeocodingBundle;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Geocoder\Geocoder;
use Geocoder\Provider\ChainProvider;
use Geocoder\Provider\GoogleMapsProvider;
use Geocoder\Provider\MapQuestProvider;
use Geocoder\Provider\NominatimProvider;
use Geocoder\Provider\YandexProvider;
use Keboola\GeocodingBundle\Service\EventLogger;
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


	public function __construct(Registry $doctrine, Temp $temp, Logger $logger, $googleApiKey, $mapQuestKey)
	{
		$adapter = new GuzzleAdapter();
		$geocoder = new Geocoder();
		$geocoder->registerProvider(new ChainProvider(array(
			new GoogleMapsProvider($adapter, null, null, true, $googleApiKey),
			new MapQuestProvider($adapter, $mapQuestKey),
			new YandexProvider($adapter),
			new NominatimProvider($adapter, 'http://nominatim.openstreetmap.org'),
		)));
		$cache = new Geotools\Cache\Doctrine($doctrine->getConnection());
		$geotools = new \League\Geotools\Geotools();
		$this->geotoolsBatch = $geotools->batch($geocoder)->setCache($cache);

		$this->temp = $temp;
		$this->logger = $logger;
	}

	public function execute(Job $job)
	{
		$params = $job->getParams();
		$forwardGeocoding = $job->getCommand() == 'geocode';

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

		$locationsInBatch = 50;
		$batchNum = 1;

		// Download file with data column to disk and read line-by-line
		// Query Geocoding API by 50 queries
		$userTableParams = $forwardGeocoding? $params['location'] : array($params['latitude'], $params['longitude']);
		$locationsFile = $this->userStorage->getTableData($params['tableId'], $userTableParams);
		$locations = array();
		$firstRow = true;
		$handle = fopen($locationsFile, "r");
		if ($handle) {
			while (($line = fgetcsv($handle)) !== false) {
				if ($firstRow) {
					$firstRow = false;
				} else {
					if (!in_array($line[0], $locations)) {

						if ($forwardGeocoding) {
							$locations[] = $line[0];
						} else {
							if ($line[0] === null || $line[1] === null || !is_numeric($line[0]) || !is_numeric($line[1])) {
								$this->eventLogger->log(sprintf('Value %s,%s is not valid coordinates', $line[0], $line[1]),
									array(), null, EventLogger::TYPE_WARN);
							} else {
								try {
									$locations[] = new \League\Geotools\Coordinate\Coordinate(array($line[0], $line[1]));
								} catch (InvalidArgumentException $e) {
									$this->eventLogger->log(sprintf('Value %s,%s is not valid coordinates', $line[0], $line[1]),
										array(), null, EventLogger::TYPE_WARN);
								}
							}
						}

						if (count($locations) >= $locationsInBatch) {
							$this->geocodeBatch($forwardGeocoding, $locations);

							$locations = array();
							$this->eventLogger->log(sprintf('Processed %d locations', $batchNum * $locationsInBatch));
							$batchNum++;
						}
					}
				}
			}
		}
		if (count($locations)) {
			$this->geocodeBatch($forwardGeocoding, $locations);
		}
		fclose($handle);

		$this->userStorage->uploadData();
	}


	public function geocodeBatch($forwardGeocoding, $queries)
	{
		$geocoded = $forwardGeocoding
			? $this->geotoolsBatch->geocode($queries)->parallel()
			: $this->geotoolsBatch->reverse($queries)->parallel();

		foreach ($geocoded as $g) {
			/** @var \League\Geotools\Batch\BatchGeocoded $g */
			$error = $forwardGeocoding
				? $g->getLatitude() == 0 && $g->getLongitude() == 0
				: !$g->getCountry();

			$data = array('query' => $g->getQuery());
			$data = array_merge($data, $g->toArray());
			$data['bounds_south'] = $data['bounds']['south'];
			$data['bounds_east'] = $data['bounds']['east'];
			$data['bounds_west'] = $data['bounds']['west'];
			$data['bounds_north'] = $data['bounds']['north'];
			unset($data['bounds']);

			$this->userStorage->save($forwardGeocoding, $data);

			if ($error) {
				$this->eventLogger->log('No result for location "' . $g->getQuery() . '" found', array(), null, EventLogger::TYPE_WARN);
			}
		}
	}

}