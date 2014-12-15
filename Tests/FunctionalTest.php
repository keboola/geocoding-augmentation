<?php
/**
 * @package geocoding-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Tests;

use Keboola\GeocodingAugmentation\JobExecutor;
use Keboola\GeocodingAugmentation\Service\SharedStorage;
use Keboola\GeocodingAugmentation\Service\UserStorage;
use Keboola\StorageApi\Client as StorageApiClient;
use Monolog\Handler\NullHandler;
use Syrup\ComponentBundle\Job\Metadata\Job;

class FunctionalTest extends AbstractTest
{
	/**
	 * @var JobExecutor
	 */
	private $jobExecutor;

	public function setUp()
	{
		parent::setUp();

		$db = \Doctrine\DBAL\DriverManager::getConnection(array(
			'driver' => 'pdo_mysql',
			'host' => DB_HOST,
			'dbname' => DB_NAME,
			'user' => DB_USER,
			'password' => DB_PASSWORD,
		));

		$stmt = $db->prepare(file_get_contents(__DIR__ . '/../db.sql'));
		$stmt->execute();

		$sharedStorage = new SharedStorage($db);

		$logger = new \Monolog\Logger('null');
		$logger->pushHandler(new NullHandler());

		$temp = new \Syrup\ComponentBundle\Filesystem\Temp(self::APP_NAME);

		$this->jobExecutor = new JobExecutor($sharedStorage, $temp, $logger, GOOGLE_KEY, MAPQUEST_KEY);
		$this->jobExecutor->setStorageApi($this->storageApiClient);
	}

	public function testForward()
	{
		$this->jobExecutor->execute(new Job(array(
			'id' => uniqid(),
			'runId' => uniqid(),
			'token' => $this->storageApiClient->getLogData(),
			'component' => self::APP_NAME,
			'command' => 'geocode',
			'params' => array(
				'tableId' => $this->dataTableId,
				'location' => 'addr'
			)
		)));

		$this->assertTrue($this->storageApiClient->tableExists(sprintf('%s.%s', $this->inBucket, UserStorage::COORDINATES_TABLE_NAME)));
		$export = $this->storageApiClient->exportTable(sprintf('%s.%s', $this->inBucket, UserStorage::COORDINATES_TABLE_NAME));
		$csv = StorageApiClient::parseCsv($export, true);
		$this->assertEquals(4, count($csv));
	}

	public function testReverse()
	{
		$this->jobExecutor->execute(new Job(array(
			'id' => uniqid(),
			'runId' => uniqid(),
			'token' => $this->storageApiClient->getLogData(),
			'component' => self::APP_NAME,
			'command' => 'reverse',
			'params' => array(
				'tableId' => $this->dataTableId,
				'latitude' => 'lat',
				'longitude' => 'lon'
			)
		)));

		$this->assertTrue($this->storageApiClient->tableExists(sprintf('%s.%s', $this->inBucket, UserStorage::LOCATIONS_TABLE_NAME)));
		$export = $this->storageApiClient->exportTable(sprintf('%s.%s', $this->inBucket, UserStorage::LOCATIONS_TABLE_NAME));
		$csv = StorageApiClient::parseCsv($export, true);
		$this->assertEquals(4, count($csv));
	}

}