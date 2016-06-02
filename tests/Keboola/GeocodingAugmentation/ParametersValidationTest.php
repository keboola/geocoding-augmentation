<?php
/**
 * @package geocoding-augmentation
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Tests;

use Keboola\GeocodingAugmentation\Exception;
use Keboola\GeocodingAugmentation\ParametersValidation;
use Keboola\Temp\Temp;

class ParametersValidationTest extends \PHPUnit_Framework_TestCase
{

    public function testValidate()
    {
        $defaultConfig = [
            'parameters' => [
                'method' => 'geocode',
                'provider' => 'google_maps',
                'apiKey' => 'key',
                'outputTable' => 'geocoding.csv',
                'inputTables' => [
                    'coordinates.csv'
                ]
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
                            'source' => 'geocoding.csv',
                            'destination' => 'in.c-main.geocoding'
                        ]
                    ]
                ]
            ]
        ];

        // should be ok
        $config = $defaultConfig;
        ParametersValidation::validate($config);

        // wrong method
        $config = $defaultConfig;
        $config['parameters']['method'] = uniqid();
        try {
            ParametersValidation::validate($config);
            $this->fail();
        } catch (Exception $e) {
        }

        // missing apiKey for google_maps provider
        $config = $defaultConfig;
        unset($config['parameters']['apiKey']);
        try {
            ParametersValidation::validate($config);
            $this->fail();
        } catch (Exception $e) {
        }

        // wrong output mapping
        $config = $defaultConfig;
        $config['storage']['output']['tables'][0]['source'] = uniqid();
        try {
            ParametersValidation::validate($config);
            $this->fail();
        } catch (Exception $e) {
        }
    }

    public function testValidateTable()
    {
        $temp = new Temp();
        $temp->initRunFolder();

        $file = $temp->createFile(uniqid());
        file_put_contents($file->getRealPath(), '1');
        ParametersValidation::validateTable('geocode', 'table', $file->getRealPath());

        $file = $temp->createFile(uniqid());
        file_put_contents($file->getRealPath(), '1,2');
        try {
            ParametersValidation::validateTable('geocode', 'table', $file->getRealPath());
            $this->fail();
        } catch (Exception $e) {
        }

        $file = $temp->createFile(uniqid());
        try {
            ParametersValidation::validateTable('geocode', 'table', $file->getRealPath());
            $this->fail();
        } catch (Exception $e) {
        }


        $file = $temp->createFile(uniqid());
        file_put_contents($file->getRealPath(), '1,2');
        ParametersValidation::validateTable('reverse', 'table', $file->getRealPath());

        $file = $temp->createFile(uniqid());
        file_put_contents($file->getRealPath(), '1');
        try {
            ParametersValidation::validateTable('reverse', 'table', $file->getRealPath());
            $this->fail();
        } catch (Exception $e) {
        }

        $file = $temp->createFile(uniqid());
        file_put_contents($file->getRealPath(), '1,2,3');
        try {
            ParametersValidation::validateTable('reverse', 'table', $file->getRealPath());
            $this->fail();
        } catch (Exception $e) {
        }
    }
}
