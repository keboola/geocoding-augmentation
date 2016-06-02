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
                'provider' => 'openstreetmap',
                'locale' => 'cs'
            ]
        );

        $this->outputFile = "{$this->temp->getTmpFolder()}/$outputTable";
    }

    public function testAugmentationGeocode()
    {
        $this->app->process('geocode', __DIR__ . '/locations.csv');
        $this->assertFileExists($this->outputFile);
        $data = new CsvFile($this->outputFile);
        $this->assertCount(3, $data);
        
        $data->next();
        $row = $data->current();
        $this->assertEquals('Brno', $row[1]);
        $this->assertEquals('openstreetmap', $row[2]);
        $this->assertEquals('cs', $row[3]);
        $this->assertEquals(49.1922443, $row[4]);
        $this->assertEquals(16.6113382, $row[5]);
        $this->assertEquals('Brno', $row[12]);
        $this->assertEquals('okres Brno-město', $row[15]);
        $this->assertEquals('Jihovýchod', $row[17]);
        $this->assertEquals('Česko', $row[19]);
        $this->assertEquals('CZ', $row[20]);

        $data->next();
        $row = $data->current();
        $this->assertEquals('Prague', $row[1]);
        $this->assertEquals('openstreetmap', $row[2]);
        $this->assertEquals('cs', $row[3]);
        $this->assertEquals(50.0874654, $row[4]);
        $this->assertEquals(14.4212503, $row[5]);
        $this->assertEquals('Praha', $row[12]);
        $this->assertEquals('okres Hlavní město Praha', $row[15]);
        $this->assertEquals('Praha', $row[17]);
        $this->assertEquals('Česko', $row[19]);
        $this->assertEquals('CZ', $row[20]);
    }

    public function testAugmentationReverse()
    {
        $this->app->process('reverse', __DIR__ . '/coordinates.csv');
        $this->assertFileExists($this->outputFile);
        $data = new CsvFile($this->outputFile);
        $this->assertCount(3, $data);

        $data->next();
        $row = $data->current();
        $this->assertEquals('49.193909, 16.613659', $row[1]);
        $this->assertEquals('openstreetmap', $row[2]);
        $this->assertEquals('cs', $row[3]);
        $this->assertEquals('Brno', $row[12]);
        $this->assertEquals('okres Brno-město', $row[15]);
        $this->assertEquals('Jihovýchod', $row[17]);
        $this->assertEquals('Česko', $row[19]);
        $this->assertEquals('CZ', $row[20]);

        $data->next();
        $row = $data->current();
        $this->assertEquals('50.075012, 14.438838', $row[1]);
        $this->assertEquals('openstreetmap', $row[2]);
        $this->assertEquals('cs', $row[3]);
        $this->assertEquals('Praha', $row[12]);
        $this->assertEquals('okres Hlavní město Praha', $row[15]);
        $this->assertEquals('Praha', $row[17]);
        $this->assertEquals('Česko', $row[19]);
        $this->assertEquals('CZ', $row[20]);
    }
}
