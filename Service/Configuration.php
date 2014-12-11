<?php
/**
 * @package geocoding-bundle
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Service;

use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Client;
use Syrup\ComponentBundle\Exception\SyrupComponentException;

class ConfigurationException extends SyrupComponentException
{
	public function __construct($message, $previous = null)
	{
		parent::__construct(400, $message, $previous);
	}
}

class Configuration
{
	/**
	 * @var \Keboola\StorageApi\Client
	 */
	protected $storageApiClient;

	const BUCKET_ID = 'sys.c-ag-geocoding';
	const TABLE_NAME = 'configuration';

	public function __construct(Client $storageApi)
	{
		$this->storageApiClient = $storageApi;
	}

	public function getConfiguration()
	{
		if (!$this->storageApiClient->bucketExists(self::BUCKET_ID)) {
			throw new ConfigurationException('Configuration bucket ' . self::BUCKET_ID . ' does not exist');
		}
		if (!$this->storageApiClient->tableExists(self::BUCKET_ID . '.' . self::TABLE_NAME)) {
			throw new ConfigurationException('Configuration table ' . self::BUCKET_ID . '.' . self::TABLE_NAME . ' does not exist');
		}

		$csv = $this->storageApiClient->exportTable(self::BUCKET_ID . '.' . self::TABLE_NAME);
		$table = StorageApiClient::parseCsv($csv, true);

		if (!count($table)) {
			throw new ConfigurationException('Configuration table ' . self::BUCKET_ID . '.' . self::TABLE_NAME . ' is empty');
		}

		if (!isset($table[0]['column']) || !isset($table[0]['conditions']) || !isset($table[0]['units'])) {
			throw new ConfigurationException('Configuration table ' . self::BUCKET_ID . '.' . self::TABLE_NAME
				. ' should contain columns column,conditions,units');
		}

		$result = array();
		foreach ($table as $t) {

			$col = explode('.', $t['column']);
			$tableId = sprintf('%s.%s.%s', $col[0], $col[1], $col[2]);
			if (count($col) != 4) {
				throw new ConfigurationException('Configured column to extract ' . $t['column'] . ' has bad format');
			}
			try {
				if (!$this->storageApiClient->tableExists($tableId)) {
					throw new ConfigurationException('Table of configured column to extract ' . $t['column'] . ' does not exist');
				}
			} catch (\Keboola\StorageApi\ClientException $e) {
				if ($e->getCode() == 403) {
					throw new ConfigurationException('Table of configured column to extract ' . $t['column'] . ' is not accessible with your token');
				} else {
					throw $e;
				}
			}
			$tableInfo = $this->storageApiClient->getTable($tableId);
			if (!in_array($col[3], $tableInfo['columns'])) {
				throw new ConfigurationException('Configured column to extract ' . $t['column'] . ' does not exist in that table');
			}

			$result[] = array(
				'tableId' => $tableId,
				'column' => $col[3]
			);
		}
		return $result;
	}
} 