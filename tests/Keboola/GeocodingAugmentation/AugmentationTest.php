<?php
/**
 * @package geocoding-augmentation
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Tests;

use Keboola\Csv\CsvFile;
use Keboola\GeocodingAugmentation\Augmentation;
use Keboola\Temp\Temp;

class AugmentationTest extends \PHPUnit_Framework_TestCase
{
    /** @var  Temp */
    protected $temp;
    /** @var  Augmentation */
    protected $app;
    protected $outputFile;

    public function setUp()
    {
        $outputTable = 't' . uniqid();

        $this->temp = new Temp();
        $this->temp->initRunFolder();

        $this->app = new \Keboola\GeocodingAugmentation\Augmentation(
            $this->temp->getTmpFolder()."/$outputTable",
            $outputTable,
            [

            ]
        );

        $this->outputFile = "{$this->temp->getTmpFolder()}/$outputTable";
        copy(__DIR__ . '/data.csv', $this->temp->getTmpFolder() . '/data1.csv');
    }

    public function testAugmentationForDefinedDates()
    {
        $this->app->process($this->temp->getTmpFolder() . '/data1.csv', 'lat', 'lon', 'time', ['temperature', 'windSpeed']);
        $this->assertFileExists($this->outputFile);
        $data = new CsvFile($this->outputFile);
        $this->assertCount(7, $data);
        $location1Count = 0;
        $location2Count = 0;
        foreach ($data as $row) {
            if ($row[1] == 49.191 && $row[2] == 16.611) {
                $location1Count++;
            }
            if ($row[1] == 50.071 && $row[2] == 14.423) {
                $location2Count++;
            }
        }
        $this->assertEquals(2, $location1Count);
        $this->assertEquals(4, $location2Count);
    }

    public function testAugmentationForToday()
    {
        $this->app->process($this->temp->getTmpFolder() . '/data1.csv', 'lat', 'lon', null, ['temperature', 'windSpeed']);
        $this->assertFileExists($this->outputFile);
        $data = new CsvFile($this->outputFile);
        $this->assertCount(5, $data);
        $location1Count = 0;
        $location2Count = 0;
        foreach ($data as $row) {
            if ($row[1] == 49.191 && $row[2] == 16.611) {
                $location1Count++;
            }
            if ($row[1] == 50.071 && $row[2] == 14.423) {
                $location2Count++;
            }
        }
        $this->assertEquals(2, $location1Count);
        $this->assertEquals(2, $location2Count);
    }
}
