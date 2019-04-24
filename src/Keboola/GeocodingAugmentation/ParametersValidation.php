<?php
namespace Keboola\GeocodingAugmentation;

use Keboola\Csv\CsvReader;

class ParametersValidation
{
    public static function validate($config)
    {
        if (!isset($config['storage']['input']['tables']) || ! count($config['storage']['input']['tables'])) {
            throw new Exception("There is no table configured in input mapping");
        }

        if (!isset($config['storage']['output']['tables']) || ! count($config['storage']['output']['tables'])) {
            throw new Exception("There is no table configured in output mapping");
        }


        if (!isset($config['parameters']['method'])) {
            throw new Exception("Missing parameter 'method'");
        }
        if (!in_array($config['parameters']['method'], [Augmentation::METHOD_GEOCODE, Augmentation::METHOD_REVERSE])) {
            throw new Exception("Parameter 'method' must have value '".Augmentation::METHOD_GEOCODE."' or '"
                . Augmentation::METHOD_REVERSE."'");
        }

        if (!isset($config['parameters']['provider'])) {
            throw new Exception("Missing parameter 'provider'");
        }
        switch ($config['parameters']['provider']) {
            case 'google_maps':
            case 'bing_maps':
            case 'map_quest':
            case 'tomtom':
            case 'opencage':
                if (!isset($config['parameters']['apiKey'])) {
                    throw new Exception("Missing parameter 'apiKey'");
                }
                break;
            case 'google_maps_business':
                if (!isset($config['parameters']['clientId'])) {
                    throw new Exception("Missing parameter 'clientId'");
                }
                if (!isset($config['parameters']['privateKey'])) {
                    throw new Exception("Missing parameter 'privateKey'");
                }
                break;

                break;
            case 'yandex':
            case 'nominatim':
                break;
            default:
                throw new Exception("Parameter 'provider' with value '{$config['parameters']['provider']}' is not "
                    . "supported");
        }
    }

    public static function validateTable($method, $table, $csvFile)
    {
        $csv = new CsvReader($csvFile);
        if ($method == Augmentation::METHOD_GEOCODE) {
            if (count($csv->getHeader()) != 1) {
                throw new Exception("Input table $table must have exactly one column with locations to geocode");
            }
        } else {
            if (count($csv->getHeader()) != 2) {
                throw new Exception("Input table $table must have exactly two columns with latitudes and longitudes to "
                    ."reverse geocode");
            }
        }
    }
}
