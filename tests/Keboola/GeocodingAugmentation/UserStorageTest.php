<?php
/**
 * @package geocoding-augmentation
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Tests;

use Keboola\GeocodingAugmentation\UserStorage;
use Symfony\Component\Yaml\Yaml;

class UserStorageTest extends \PHPUnit_Framework_TestCase
{

    public function testSave()
    {
        $temp = new \Keboola\Temp\Temp();
        $temp->initRunFolder();
        $table = 'in.c-ag-geocoding.geocoding';

        $userStorage = new UserStorage($temp->getTmpFolder()."/$table", $table);
        $userStorage->save([
            'primary' => 'key',
            'query' => 'test',
            'provider' => 'yandex',
            'locale' => 'en',
            'latitude' => '10.5',
            'longitude' => '13.4'
        ]);

        $this->assertTrue(file_exists("{$temp->getTmpFolder()}/$table"));
        if (($handle = fopen("{$temp->getTmpFolder()}/$table", "r")) !== false) {
            $row1 = fgetcsv($handle, 1000, ",");
            $this->assertEquals(["primary","latitude","longitude","date","key","value"], $row1);
            $row2 = fgetcsv($handle, 1000, ",");
            $this->assertEquals(["key","10.5","13.4","2016-01-01","temperature","-12.5"], $row2);
            fclose($handle);
        } else {
            $this->fail();
        }

        $this->assertTrue(file_exists("{$temp->getTmpFolder()}/$table.manifest"));
        $manifest = Yaml::parse(file_get_contents("{$temp->getTmpFolder()}/$table.manifest"));
        $this->assertArrayHasKey('destination', $manifest);
        $this->assertEquals($table, $manifest['destination']);
        $this->assertArrayHasKey('incremental', $manifest);
        $this->assertEquals(true, $manifest['incremental']);
        $this->assertArrayHasKey('primary_key', $manifest);
        $this->assertEquals("primary", $manifest['primary_key']);
    }
}
