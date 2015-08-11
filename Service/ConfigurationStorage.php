<?php
/**
 * @package forecastio-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Service;

use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Client;

class ConfigurationStorage
{
    /**
     * @var \Keboola\StorageApi\Client
     */
    protected $storageApiClient;

    const BUCKET_ID = 'sys.c-ag-geocoding';

    const METHOD_GEOCODE = 'geocode';
    const METHOD_REVERSE = 'reverse';

    public function __construct(Client $storageApi)
    {
        $this->storageApiClient = $storageApi;
    }

    public function getConfigurationsList()
    {
        if (!$this->storageApiClient->bucketExists(self::BUCKET_ID)) {
            throw new ConfigurationException(sprintf('Configuration bucket %s does not exist', self::BUCKET_ID));
        }
        $result = array();
        foreach ($this->storageApiClient->listTables(self::BUCKET_ID) as $table) {
            $result[] = $table['name'];
        }
        return $result;
    }

    public function getConfiguration($config)
    {
        $configTableId = sprintf('%s.%s', self::BUCKET_ID, $config);

        if (!$this->storageApiClient->bucketExists(self::BUCKET_ID)) {
            throw new ConfigurationException(sprintf('Configuration bucket %s does not exist', self::BUCKET_ID));
        }
        if (!$this->storageApiClient->tableExists($configTableId)) {
            throw new ConfigurationException(sprintf('Configuration table %s does not exist', $configTableId));
        }

        $csv = $this->storageApiClient->exportTable($configTableId);
        $table = StorageApiClient::parseCsv($csv, true);

        $attrs = [];
        $tableInfo = $this->storageApiClient->getTable($configTableId);
        foreach ($tableInfo['attributes'] as $attr) {
            $attrs[$attr['name']] = $attr['value'];
        }
        if (!isset($attrs['method']) || !in_array($attrs['method'], array(self::METHOD_GEOCODE, self::METHOD_REVERSE))) {
            throw new ConfigurationException(sprintf(
                "Configuration table '%s' must have attribute 'method' with value '%s' or '%s'",
                $configTableId,
                self::METHOD_GEOCODE,
                self::METHOD_REVERSE
            ));
        }

        if (!count($table)) {
            throw new ConfigurationException(sprintf('Configuration table %s is empty', $configTableId));
        }

        if ($attrs['method'] == self::METHOD_GEOCODE) {
            if (!isset($table[0]['tableId']) || !isset($table[0]['addressCol'])) {
                throw new ConfigurationException(sprintf("Configuration table '%s' should contain columns 'tableId,addressCol'", $configTableId));
            }
        } else {
            if (!isset($table[0]['tableId']) || !isset($table[0]['latitudeCol']) || !isset($table[0]['longitudeCol'])) {
                throw new ConfigurationException(sprintf("Configuration table '%s' should contain columns 'tableId,latitudeCol,longitudeCol'", $configTableId));
            }
        }

        $result = $attrs;
        $result['tables'] = array();
        foreach ($table as $t) {
            try {
                if (!$this->storageApiClient->tableExists($t['tableId'])) {
                    throw new ConfigurationException(sprintf('Data table %s does not exist', $t['tableId']));
                }
            } catch (\Keboola\StorageApi\ClientException $e) {
                if ($e->getCode() == 403) {
                    throw new ConfigurationException(sprintf('Data table %s is not accessible with your token', $t['tableId']));
                } else {
                    throw $e;
                }
            }
            $tableInfo = $this->storageApiClient->getTable($t['tableId']);

            if ($attrs['method'] == self::METHOD_GEOCODE) {
                if (!in_array($t['addressCol'], $tableInfo['columns'])) {
                    throw new ConfigurationException(sprintf("Column '%s' does not exist in table '%s'", $t['addressCol'], $t['tableId']));
                }

                $result['tables'][] = array(
                    'tableId' => $t['tableId'],
                    'addressCol' => $t['addressCol']
                );
            } else {
                if (!in_array($t['latitudeCol'], $tableInfo['columns'])) {
                    throw new ConfigurationException(sprintf("Column '%s' does not exist in table '%s'", $t['latitudeCol'], $t['tableId']));
                }
                if (!in_array($t['longitudeCol'], $tableInfo['columns'])) {
                    throw new ConfigurationException(sprintf("Column '%s' does not exist in table '%s'", $t['longitudeCol'], $t['tableId']));
                }

                $result['tables'][] = array(
                    'tableId' => $t['tableId'],
                    'latitudeCol' => $t['latitudeCol'],
                    'longitudeCol' => $t['longitudeCol']
                );
            }
        }
        return $result;
    }
}
