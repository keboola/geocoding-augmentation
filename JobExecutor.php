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
use Monolog\Logger;
use Syrup\ComponentBundle\Filesystem\Temp;
use Syrup\ComponentBundle\Job\Metadata\Job;

class JobExecutor extends \Syrup\ComponentBundle\Job\Executor
{
	protected $googleApiKey;
	protected $mapQuestKey;

	/**
	 * @var SharedStorage
	 */
	protected $sharedStorage;
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


	public function __construct(SharedStorage $sharedStorage, Temp $temp, Logger $logger, $googleApiKey, $mapQuestKey)
	{
		$this->sharedStorage = $sharedStorage;
		$this->temp = $temp;
		$this->logger = $logger;

		$this->googleApiKey = $googleApiKey;
		$this->mapQuestKey = $mapQuestKey;
	}

	public function execute(Job $job)
	{
		$this->eventLogger = new EventLogger($this->storageApi, $job->getId());
		$this->userStorage = new UserStorage($this->storageApi, $this->temp);

		$addressesInBatch = 50;
		$batchNum = 1;

		$params = $job->getParams();

		// Download file with data column to disk and read line-by-line
		// Query Geocoding API by 50 addresses
		$locationsFile = $this->userStorage->getTableColumnData($params['tableId'], $params['column']);
		$locations = array();
		$firstRow = true;
		$handle = fopen($locationsFile, "r");
		if ($handle) {
			while (($line = fgetcsv($handle)) !== false) {
				if ($firstRow) {
					$firstRow = false;
				} else {
					if (!in_array($line[0], $locations)) {
						$locations[] = $line[0];
						if (count($locations) >= $addressesInBatch) {
							$this->process($locations);
							$locations = array();
							$this->eventLogger->log(sprintf('Processed %d addresses', $batchNum * $addressesInBatch));
							$batchNum++;
						}
					}
				}
			}
		}
		if (count($locations)) {
			$this->process($locations);
		}
		fclose($handle);

	}

	public function process($locations)
	{
		$coordinates = $this->getCoordinates($locations);
		$this->userStorage->saveCoordinates($coordinates);
	}


	public function getCoordinates($locations)
	{
		$result = array();
		$savedLocations = $this->sharedStorage->getSavedLocations($locations);

		$locationsToSave = array();
		foreach ($locations as $loc) {
			if (!isset($savedLocations[$loc])) {
				if (!in_array($loc, $locationsToSave)) {
					$locationsToSave[] = $loc;
				}
			} else {
				$result[] = array(
					'address' => $loc,
					'latitude' => $savedLocations[$loc]['latitude'],
					'longitude' => $savedLocations[$loc]['longitude']
				);
			}
		}

		if (count($locationsToSave)) {
			$adapter = new GuzzleAdapter();
			$geocoder = new Geocoder();
			$geocoder->registerProvider(new ChainProvider(array(
				new GoogleMapsProvider($adapter, null, null, true, $this->googleApiKey),
				new MapQuestProvider($adapter, $this->mapQuestKey),
				new YandexProvider($adapter),
				new NominatimProvider($adapter, 'http://nominatim.openstreetmap.org'),
			)));
			$geotools = new \League\Geotools\Geotools();

			$geocoded = $geotools->batch($geocoder)->geocode($locationsToSave)->parallel();
			foreach ($geocoded as $g) {
				/** @var \League\Geotools\Batch\BatchGeocoded $g */
				$error = $g->getLatitude() == 0 && $g->getLongitude() == 0;
				$result[] = array(
					'address' => $g->getQuery(),
					'latitude' => $error? '-' : $g->getLatitude(),
					'longitude' => $error? '-' : $g->getLongitude()
				);
				$this->sharedStorage->saveLocation(
					$g->getQuery(),
					$g->getLatitude(),
					$g->getLongitude(),
					$g->getProviderName(),
					$g->getExceptionMessage()
				);
				if ($error) {
					$this->eventLogger->log('No coordinates for address "' . $g->getQuery() . '" found', array(), null, EventLogger::TYPE_WARN);
				}
			}
		}

		return $result;
	}

}