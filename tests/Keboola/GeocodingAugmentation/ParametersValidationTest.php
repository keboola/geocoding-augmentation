<?php
namespace Keboola\GeocodingAugmentation\Tests;

use Keboola\GeocodingAugmentation\Exception;
use Keboola\GeocodingAugmentation\ParametersValidation;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;

class ParametersValidationTest extends TestCase
{

    public function testValidate()
    {
        $defaultConfig = [
            'parameters' => [
                'method' => 'geocode',
                'provider' => 'google_maps',
                'apiKey' => 'key'
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

        $this->assertTrue(true);
    }

    public function testValidateTable()
    {
        $temp = new Temp();

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

        $this->assertTrue(true);
    }
}
