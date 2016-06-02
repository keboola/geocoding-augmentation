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
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{

    public function testFunctional()
    {
        $temp = new Temp();
        $temp->initRunFolder();

        file_put_contents($temp->getTmpFolder() . '/config.yml', Yaml::dump([
            'storage' => [
                'input' => [
                    'tables' => [
                        [
                            'source' => 'in.c-main.coordinates',
                            'destination' => 'coordinates.csv'
                        ]
                    ]
                ],
                'output' => [
                    'tables' => [
                        [
                            'destination' => 'in.c-main.geocoding',
                            'source' => 'geocoding.csv'
                        ]
                    ]
                ]
            ],
            'parameters' => [
                'method' => 'reverse',
                'provider' => 'openstreetmap',
                'outputTable' => 'geocoding.csv',
                'inputTables' => [
                    'coordinates.csv'
                ]
            ]
        ]));

        mkdir($temp->getTmpFolder().'/in');
        mkdir($temp->getTmpFolder().'/in/tables');
        copy(__DIR__ . '/coordinates.csv', $temp->getTmpFolder().'/in/tables/coordinates.csv');
        copy(__DIR__ . '/coordinates.csv.manifest', $temp->getTmpFolder().'/in/tables/coordinates.csv.manifest');

        $process = new Process("php ".__DIR__."/../../../src/run.php --data=".$temp->getTmpFolder());
        $process->setTimeout(null);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->fail($process->getOutput().PHP_EOL.$process->getErrorOutput());
        }

        $this->assertFileExists("{$temp->getTmpFolder()}/out/tables/geocoding.csv");
    }
}
