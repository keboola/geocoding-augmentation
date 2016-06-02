<?php
/**
 * @package geocoding-augmentation
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GeocodingAugmentation;

class ParametersValidation
{
    public static function validate($config)
    {
        if (!isset($config['parameters']['inputTables'])) {
            throw new Exception("Missing parameter 'inputTables'");
        }

        if (!isset($config['parameters']['outputTable'])) {
            throw new Exception("Missing parameter outputTable");
        }
        if (!isset($config['storage']['output']['tables'][0]['destination'])) {
            throw new Exception("Destination table is not connected to output mapping");
        }
        if ($config['parameters']['outputTable'] != $config['storage']['output']['tables'][0]['source']) {
            throw new Exception("Parameter 'outputTable' with value '{$config['parameters']['outputTable']}' does not "
                . "correspond to table connected using output mapping: "
                . "'{$config['storage']['output']['tables'][0]['source']}' for table "
                . "({$config['storage']['output']['tables'][0]['destination']}) ");
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
            case 'openstreetmap':
                break;
            default:
                throw new Exception("Parameter 'provider' with value '{$config['parameters']['provider']}' is not "
                    . "supported");
        }
    }

    public static function validateTable($method, $table, $manifest)
    {
        if ($method == Augmentation::METHOD_GEOCODE) {
            if (count($manifest['columns']) != 1) {
                throw new Exception("Input table $table must have exactly one column with locations to geocode");
            }
        } else {
            if (count($manifest['columns']) != 2) {
                throw new Exception("Input table $table must have exactly two columns with latitudes and logitudes to "
                    ."reverse geocode");
            }
        }
    }
}
