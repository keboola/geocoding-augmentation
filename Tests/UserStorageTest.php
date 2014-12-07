<?php
/**
 * @package geocoding-bundle
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingBundle\Tests;

use Keboola\Csv\CsvFile;
use Keboola\GeocodingBundle\Service\UserStorage;
use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Table;

class UserStorageTest extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{

	public function testDownload()
	{
		$container = static::createClient()->getContainer();
		$storageApiClient = new StorageApiClient(array(
			'token' => $container->getParameter('storage_api.test.token'),
			'url' => $container->getParameter('storage_api.test.url'))
		);
		$tableId = 'out.c-main.' . uniqid();

		// Prepare data table
		$t = new Table($storageApiClient, $tableId);
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

		$userStorage = new UserStorage($storageApiClient, $container->get('syrup.temp'));

		$csv1 = new CsvFile($userStorage->getData($tableId, 'addr'));
		$csv2 = new CsvFile($userStorage->getData($tableId, array('lat', 'lon')));

		$storageApiClient->dropTable($tableId);

		$data1 = array();
		foreach ($csv1 as $r) {
			$data1[] = $r[0];
		}
		$this->assertEquals(array('Brno', 'Ostrava', 'Plzeň', 'Praha'), $data1);

		$data2 = array();
		foreach ($csv2 as $r) {
			$data2[] = $r[0];
		}
		$this->assertEquals(array(
			array("35.235","57.453"),
			array("35.235","57.553"),
			array("35.333","57.333"),
			array("36.234","56.443")), $data2);


	}

}