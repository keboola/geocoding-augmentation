<?php
/**
 * @package geocoding-augmentation
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GeocodingAugmentation;

use Keboola\Csv\CsvFile;
use Symfony\Component\Yaml\Yaml;

class UserStorage
{
    protected static $columns = ['primary', 'query', 'provider', 'locale', 'latitude', 'longitude', 'bounds_south',
        'bounds_east', 'bounds_west', 'bounds_north', 'streetNumber', 'streetName', 'city', 'zipcode', 'cityDistrict',
        'county', 'countyCode', 'region', 'regionCode', 'country', 'countryCode', 'timezone', 'exceptionMessage'];
    protected static $primaryKey = ['primary'];

    protected $outputFile;
    protected $destination;
    protected $file;

    public function __construct($outputFile, $destination)
    {
        $this->outputFile = $outputFile;
        $this->destination = $destination;
    }

    public function save($data)
    {
        if (!$this->file) {
            $this->file = new CsvFile($this->outputFile);
            $this->file->writeRow(self::$columns);

            file_put_contents("$this->outputFile.manifest", Yaml::dump([
                'destination' => $this->destination,
                'incremental' => true,
                'primary_key' => self::$primaryKey
            ]));
        }

        $dataToSave = ['primary' => md5($data['query'].':'.$data['provider'].':'.$data['locale'])];
        foreach (self::$columns as $c) {
            $dataToSave[$c] = isset($data[$c]) ? $data[$c] : null;
        }

        $this->file->writeRow($dataToSave);
    }
}
