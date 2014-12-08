<?php
/**
 * Created by IntelliJ IDEA.
 * User: JakubM
 * Date: 08.09.14
 * Time: 13:46
 */

namespace Keboola\GeocodingBundle\Service;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\TableExporter;
use League\Geotools\Batch\BatchGeocoded;
use Symfony\Component\Process\Process;
use Syrup\ComponentBundle\Exception\SyrupComponentException;
use Syrup\ComponentBundle\Exception\UserException;
use Syrup\ComponentBundle\Filesystem\Temp;

class UserStorage
{
	/**
	 * @var \Keboola\StorageApi\Client
	 */
	protected $storageApiClient;
	/**
	 * @var \Syrup\ComponentBundle\Filesystem\Temp
	 */
	protected $temp;
	protected $files = array();

	const BUCKET_NAME = 'ag-geocoding';
	const BUCKET_ID = 'in.c-ag-geocoding';
	const COORDINATES_TABLE_NAME = 'coordinates';
	const LOCATIONS_TABLE_NAME = 'locations';

	public $tables = array(
		'columns' => array('query', 'provider', 'latitude', 'longitude', 'bounds_south', 'bounds_east', 'bounds_west',
			'bounds_north', 'streetNumber', 'streetName', 'city', 'zipcode', 'cityDistrict', 'county', 'countyCode',
			'region', 'regionCode', 'country', 'countryCode', 'timezone', 'exceptionMessage'),
		'primaryKey' => 'query'
	);


	public function __construct(Client $storageApi, Temp $temp)
	{
		$this->storageApiClient = $storageApi;
		$this->temp = $temp;
	}

	public function save($forward, $data)
	{
		$table = $forward? self::COORDINATES_TABLE_NAME : self::LOCATIONS_TABLE_NAME;

		if (!isset($this->files[$table])) {
			$this->files[$table] = new CsvFile($this->temp->createTmpFile());
			$this->files[$table]->writeRow($this->tables['columns']);
		}
		$this->files[$table]->writeRow($data);
	}

	public function uploadData()
	{
		if (!$this->storageApiClient->bucketExists(self::BUCKET_ID)) {
			$this->storageApiClient->createBucket(self::BUCKET_NAME, 'in', 'Geocoding Data Storage');
		}

		foreach($this->files as $name => $file) {
			$tableId = self::BUCKET_ID . "." . $name;
			try {
				$options = array();
				if (!empty($this->tables['primaryKey'])) {
					$options['primaryKey'] = $this->tables['primaryKey'];
				}
				if(!$this->storageApiClient->tableExists($tableId)) {
					$this->storageApiClient->createTableAsync(self::BUCKET_ID, $name, $file, $options);
				} else {
					$this->storageApiClient->writeTableAsync($tableId, $file, $options);
				}
			} catch(\Keboola\StorageApi\ClientException $e) {
				throw new UserException($e->getMessage(), $e);
			}
		}
	}

	public function getData($tableId, $columns)
	{
		// Get from SAPI
		$downloadedFile = $this->temp->createTmpFile();
		$params = array(
			'format' => 'escaped',
			'columns' => is_array($columns)? $columns : array($columns)
		);
		try {
			$exporter = new TableExporter($this->storageApiClient);
			$exporter->exportTable($tableId, $downloadedFile->getRealPath(), $params);
		} catch (ClientException $e) {
			if ($e->getCode() == 404) {
				throw new UserException($e->getMessage(), $e);
			} else {
				throw $e;
			}
		}

		if (!file_exists($downloadedFile->getRealPath())) {
			$e = new SyrupComponentException(500, 'Download from SAPI failed');
			$e->setData(array(
				'tableId' => $tableId,
				'columns' => $columns
			));
			throw $e;
		}

		// Deduplicate data
		$processedFile = $this->temp->createTmpFile();
		$process = new Process(sprintf('sed -e "1d" %s | sort | uniq > %s', $downloadedFile->getRealPath(), $processedFile->getRealPath()));
		$process->setTimeout(null);
		$process->run();
		$error = $process->getErrorOutput();
		$output = $process->getOutput();

		if ($process->isSuccessful() && !$error && file_exists($processedFile->getRealPath())) {
			return $processedFile;
		} else {
			$e = new SyrupComponentException(500, 'Deduplication failed');
			$e->setData(array(
				'tableId' => $tableId,
				'columns' => $columns,
				'error' => $error,
				'output' => $output
			));
			throw $e;
		}
	}
} 