<?php
namespace Keboola\GeocodingAugmentation\Tests;

use Keboola\Csv\CsvReader;
use Keboola\GeocodingAugmentation\Augmentation;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;

class AugmentationTest extends TestCase
{
    /** @var  Temp */
    protected $temp;

    public function setUp(): void
    {
        $this->temp = new Temp();
    }

    public function testAugmentationGeocode()
    {
        $outputTable = 't' . uniqid();
        $outputFile = "{$this->temp->getTmpFolder()}/$outputTable";
        $app = new \Keboola\GeocodingAugmentation\Augmentation(
            $this->temp->getTmpFolder()."/$outputTable",
            $outputTable,
            [
                'provider' => 'nominatim',
                'locale' => 'cs'
            ]
        );
        $app->process('geocode', __DIR__ . '/locations.csv');

        $this->assertFileExists($outputFile);
        $data = new CsvReader($outputFile);
        $this->assertCount(3, $data);

        $data->next();
        $row = $data->current();
        $this->assertEquals('Brno', $row[1]);
        $this->assertEquals('nominatim', $row[2]);
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
        $this->assertEquals('nominatim', $row[2]);
        $this->assertEquals('cs', $row[3]);
        $this->assertEquals(50.0874654, $row[4]);
        $this->assertEquals(14.4212535, $row[5]);
        $this->assertEquals('Praha', $row[12]);
        $this->assertEquals('okres Hlavní město Praha', $row[15]);
        $this->assertEquals('Praha', $row[17]);
        $this->assertEquals('Česko', $row[19]);
        $this->assertEquals('CZ', $row[20]);
    }

    public function testAugmentationReverse()
    {
        $outputTable = 't' . uniqid();
        $outputFile = "{$this->temp->getTmpFolder()}/$outputTable";
        $app = new \Keboola\GeocodingAugmentation\Augmentation(
            $this->temp->getTmpFolder()."/$outputTable",
            $outputTable,
            [
                'provider' => 'nominatim',
                'locale' => 'cs'
            ]
        );
        $app->process('reverse', __DIR__ . '/coordinates.csv');

        $this->assertFileExists($outputFile);
        $data = new CsvReader($outputFile);
        $this->assertCount(3, $data);

        $data->next();
        $row = $data->current();
        $this->assertEquals('49.193909, 16.613659', $row[1]);
        $this->assertEquals('nominatim', $row[2]);
        $this->assertEquals('cs', $row[3]);
        $this->assertEquals('Brno', $row[12]);
        $this->assertEquals('okres Brno-město', $row[15]);
        $this->assertEquals('Jihovýchod', $row[17]);
        $this->assertEquals('Česko', $row[19]);
        $this->assertEquals('CZ', $row[20]);

        $data->next();
        $row = $data->current();
        $this->assertEquals('50.075012, 14.438838', $row[1]);
        $this->assertEquals('nominatim', $row[2]);
        $this->assertEquals('cs', $row[3]);
        $this->assertEquals('Praha', $row[12]);
        $this->assertEquals('okres Hlavní město Praha', $row[15]);
        $this->assertEquals('Praha', $row[17]);
        $this->assertEquals('Česko', $row[19]);
        $this->assertEquals('CZ', $row[20]);
    }

    public function testAugmentationGoogle()
    {
        $outputTable = 't' . uniqid();
        $outputFile = "{$this->temp->getTmpFolder()}/$outputTable";
        $app = new \Keboola\GeocodingAugmentation\Augmentation(
            $this->temp->getTmpFolder()."/$outputTable",
            $outputTable,
            [
                'provider' => 'google_maps',
                'locale' => 'cs',
                'apiKey' => getenv('GOOGLE_MAPS_API_KEY')
            ]
        );
        $app->process('geocode', __DIR__ . '/locations.csv');

        $this->assertFileExists($outputFile);
        $data = new CsvReader($outputFile);
        $this->assertCount(3, $data);

        $data->next();
        $row = $data->current();
        $this->assertEquals('google_maps', $row[2]);
        $this->assertEquals('cs', $row[3]);
        $this->assertEquals('Brno', $row[12]);
        $this->assertEquals('CZ', $row[20]);

        $data->next();
        $row = $data->current();
        $this->assertEquals('google_maps', $row[2]);
        $this->assertEquals('cs', $row[3]);
        $this->assertEquals('Praha', $row[12]);
        $this->assertEquals('CZ', $row[20]);
    }
}
