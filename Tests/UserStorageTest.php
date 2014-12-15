<?php
/**
 * @package geocoding-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Tests;

use Keboola\Csv\CsvFile;
use Keboola\GeocodingAugmentation\Service\UserStorage;

class UserStorageTest extends AbstractTest
{

	public function testDownload()
	{
		$temp = new \Syrup\ComponentBundle\Filesystem\Temp('ag-geocoding');
		$userStorage = new UserStorage($this->storageApiClient, $temp);

		$csv1 = new CsvFile($userStorage->getData($this->dataTableId, 'addr'));
		$csv2 = new CsvFile($userStorage->getData($this->dataTableId, array('lat', 'lon')));

		$data1 = array();
		foreach ($csv1 as $r) {
			$data1[] = $r[0];
		}
		$this->assertEquals(array('Brno', 'Ostrava', 'Plzeň', 'Praha'), $data1);

		$data2 = array();
		foreach ($csv2 as $r) {
			$data2[] = array($r[0], $r[1]);
		}
		$this->assertEquals(array(
			array("35.235","57.453"),
			array("35.235","57.553"),
			array("35.333","57.333"),
			array("36.234","56.443")), $data2);
	}

}