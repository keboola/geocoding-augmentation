<?php
namespace Keboola\GeocodingAugmentation;

use Keboola\Csv\CsvWriter;

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

        $this->file = new CsvWriter($this->outputFile);
        $this->file->writeRow(self::$columns);

        file_put_contents("$this->outputFile.manifest", json_encode([
            'destination' => $this->destination,
            'incremental' => true,
            'primary_key' => self::$primaryKey
        ]));
    }

    public function save($data)
    {
        $dataToSave = ['primary' => md5($data['query'].':'.$data['provider'].':'.$data['locale'])];
        foreach (self::$columns as $c) {
            $dataToSave[$c] = isset($data[$c]) ? $data[$c] : null;
        }

        $this->file->writeRow($dataToSave);
    }
}
