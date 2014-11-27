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
use Keboola\StorageApi\Table as StorageApiTable;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\TableExporter;
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
	const COOORDINATES_TABLE_NAME = 'coordinates';

	public $tables = array(
		self::COOORDINATES_TABLE_NAME => array(
			'columns' => array('address', 'latitude', 'longitude'),
			'primaryKey' => 'address',
			'indices' => array()
		)
	);


	public function __construct(Client $storageApi, Temp $temp)
	{
		$this->storageApiClient = $storageApi;
		$this->temp = $temp;
	}

	public function saveCoordinates($data)
	{
		if (!isset($this->files[self::COOORDINATES_TABLE_NAME])) {
			$this->files[self::COOORDINATES_TABLE_NAME] = new CsvFile($this->temp->createTmpFile());
			$this->files[self::COOORDINATES_TABLE_NAME]->writeRow($this->tables[self::COOORDINATES_TABLE_NAME]['columns']);
		}
		$this->files[self::COOORDINATES_TABLE_NAME]->writeRow($data);
	}

	public function uploadData()
	{
		if (!$this->storageApiClient->bucketExists(self::BUCKET_ID)) {
			$this->storageApiClient->createBucket(self::BUCKET_NAME, 'in', 'Geocoding Data Storage');
		}

		foreach($this->files as $name => $file) {
			$tableId = self::BUCKET_ID . "." . $name;
			try {
				$options = array(
					'incremental' => true
				);
				if (!empty($this->tables[$name]['primaryKey'])) {
					$options['primaryKey'] = $this->tables[$name]['primaryKey'];
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

	public function getTableColumnData($tableId, $column)
	{
		$params = array(
			'format' => 'escaped',
			'columns' => array($column)
		);

		$file = $this->temp->createTmpFile();
		$fileName = $file->getRealPath();
		try {
			$exporter = new TableExporter($this->storageApiClient);
			$exporter->exportTable($tableId, $fileName, $params);
		} catch (ClientException $e) {
			if ($e->getCode() == 404) {
				throw new UserException($e->getMessage(), $e);
			} else {
				throw $e;
			}
		}

		return $fileName;
	}
} 