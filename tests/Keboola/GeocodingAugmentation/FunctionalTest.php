<?php
/**
 * @package geocoding-augmentation
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Tests;

use Doctrine\DBAL\Connection;
use Keboola\Csv\CsvFile;
use Keboola\ForecastIoAugmentation\Augmentation;
use Keboola\Temp\Temp;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{

    public function testFunctional()
    {
        $dbParams = [
            'driver' => 'pdo_mysql',
            'host' => DB_HOST,
            'dbname' => DB_NAME,
            'user' => DB_USER,
            'password' => DB_PASSWORD,
        ];

        $temp = new Temp();
        $temp->initRunFolder();

        file_put_contents($temp->getTmpFolder() . '/config.yml', Yaml::dump([
            'image_parameters' => [
                '#api_token' => FORECASTIO_KEY,
                'database' => [
                    'driver' => 'pdo_mysql',
                    '#host' => DB_HOST,
                    '#name' => DB_NAME,
                    '#user' => DB_USER,
                    '#password' => DB_PASSWORD
                ],
            ],
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
                            'destination' => 'in.c-main.conditions',
                            'source' => 'conditions.csv'
                        ]
                    ]
                ]
            ],
            'parameters' => [
                'outputTable' => 'conditions.csv',
                'inputTables' => [
                    [
                        'filename' => 'coordinates.csv',
                        'latitude' => 'lat',
                        'longitude' => 'lon'
                    ]
                ]
            ]
        ]));

        mkdir($temp->getTmpFolder().'/in');
        mkdir($temp->getTmpFolder().'/in/tables');
        copy(__DIR__ . '/data.csv', $temp->getTmpFolder().'/in/tables/coordinates.csv');
        copy(__DIR__ . '/data.csv.manifest', $temp->getTmpFolder().'/in/tables/coordinates.csv.manifest');

        $process = new Process("php ".__DIR__."/../../../src/run.php --data=".$temp->getTmpFolder());
        $process->setTimeout(null);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->fail($process->getOutput().PHP_EOL.$process->getErrorOutput());
        }

        $this->assertFileExists("{$temp->getTmpFolder()}/out/tables/conditions.csv");
    }
}
