<?php
/**
 * @package geocoding-bundle
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingBundle\Tests;

use Keboola\Csv\CsvFile;
use Keboola\GeocodingBundle\JobExecutor;
use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Table;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Syrup\ComponentBundle\Job\Metadata\Job;

class FunctionalTest extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{
	/**
	 * @var StorageApiClient
	 */
	private $storageApiClient;
	private $tableId;
	/**
	 * @var ContainerInterface
	 */
	private $container;

	public function setUp()
	{
		$this->container = static::createClient()->getContainer();
		$this->storageApiClient = new StorageApiClient(array(
			'token' => $this->container->getParameter('storage_api.test.token'),
			'url' => $this->container->getParameter('storage_api.test.url'))
		);

		$this->tableId = 'out.c-main.' . uniqid();

		// Prepare data table
		$t = new Table($this->storageApiClient, $this->tableId);
		$t->setHeader(array('addr', 'lat', 'lon'));
		$t->setFromArray(array(
			array('Praha', '35.235', '57.453'),
			array('Brno', '36.234', '56.443'),
			array('Ostrava', '35.235', '57.453'),
			array('Praha', '35.235', '57.553'),
			array('Plzeň', '35.333', '57.333'),
			array('Brno', '35.235', '57.453')
		));
		$t->save();

		if ($this->storageApiClient->tableExists('in.c-ag-geocoding.coordinates')) {
			$this->storageApiClient->dropTable('in.c-ag-geocoding.coordinates');
		}
	}

	public function tearDown()
	{
		$this->storageApiClient->dropTable($this->tableId);
	}

	public function testForward()
	{
		$job = new Job(array(
			'id' => uniqid(),
			'runId' => uniqid(),
			'token' => $this->storageApiClient->getLogData(),
			'component' => 'ag-geocoding',
			'command' => 'geocode',
			'params' => array(
				'tableId' => $this->tableId,
				'location' => 'addr'
			)
		));

		$jobExecutor = new JobExecutor(
			$this->container->get('ag_geocoding.shared_storage'),
			$this->container->get('syrup.temp'),
			$this->container->get('logger'),
			$this->container->getParameter('google_key'),
			$this->container->getParameter('mapquest_key')
		);
		$jobExecutor->setStorageApi($this->storageApiClient);
		$jobExecutor->execute($job);

		$this->assertTrue($this->storageApiClient->tableExists('in.c-ag-geocoding.coordinates'));
		$export = $this->storageApiClient->exportTable('in.c-ag-geocoding.coordinates');
		$csv = StorageApiClient::parseCsv($export, true);
		$this->assertEquals(4, count($csv));
	}

	public function testReverse()
	{
		$job = new Job(array(
			'id' => uniqid(),
			'runId' => uniqid(),
			'token' => $this->storageApiClient->getLogData(),
			'component' => 'ag-geocoding',
			'command' => 'reverse',
			'params' => array(
				'tableId' => $this->tableId,
				'latitude' => 'lat',
				'longitude' => 'lon'
			)
		));

		$jobExecutor = new JobExecutor(
			$this->container->get('ag_geocoding.shared_storage'),
			$this->container->get('syrup.temp'),
			$this->container->get('logger'),
			$this->container->getParameter('google_key'),
			$this->container->getParameter('mapquest_key')
		);
		$jobExecutor->setStorageApi($this->storageApiClient);
		$jobExecutor->execute($job);

		$this->assertTrue($this->storageApiClient->tableExists('in.c-ag-geocoding.locations'));
		$export = $this->storageApiClient->exportTable('in.c-ag-geocoding.locations');
		$csv = StorageApiClient::parseCsv($export, true);
		$this->assertEquals(4, count($csv));
	}

}