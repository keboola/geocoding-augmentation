<?php
namespace Keboola\GeocodingAugmentation\Tests;

use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class FunctionalTest extends TestCase
{

    public function testFunctional()
    {
        $temp = new Temp();

        file_put_contents($temp->getTmpFolder() . '/config.json', json_encode([
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

        $process = new Process(['php', __DIR__.'/../../../src/run.php', '--data='.$temp->getTmpFolder()]);
        $process->setTimeout(null);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->fail($process->getOutput().PHP_EOL.$process->getErrorOutput());
        }

        $this->assertFileExists("{$temp->getTmpFolder()}/out/tables/geocoding.csv");
    }

    public function testFunctionalEmptyInput()
    {
        $temp = new Temp();

        file_put_contents($temp->getTmpFolder() . '/config.json', json_encode([
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
        file_put_contents($temp->getTmpFolder().'/in/tables/coordinates.csv', 'lat,lon');
        copy(__DIR__ . '/coordinates.csv.manifest', $temp->getTmpFolder().'/in/tables/coordinates.csv.manifest');

        $process = new Process(['php', __DIR__.'/../../../src/run.php', '--data='.$temp->getTmpFolder()]);
        $process->setTimeout(null);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->fail($process->getOutput().PHP_EOL.$process->getErrorOutput());
        }

        $this->assertFileExists("{$temp->getTmpFolder()}/out/tables/geocoding.csv");
        $this->assertCount(1, file("{$temp->getTmpFolder()}/out/tables/geocoding.csv"));
    }
}
