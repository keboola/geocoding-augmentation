<?php
/**
 * @package geocoding-bundle
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingBundle\Geotools\Cache;

use Doctrine\DBAL\Connection;
use League\Geotools\Batch\BatchGeocoded;
use League\Geotools\Cache\AbstractCache;
use League\Geotools\Cache\CacheInterface;
use League\Geotools\Exception\RuntimeException;

class Doctrine extends AbstractCache implements CacheInterface
{

	const TABLE_NAME = 'geotools_cache';

	/**
	 * @var Connection
	 */
	private $connection;
	private $tableName;

	public function __construct(Connection $connection, $tableName=self::TABLE_NAME)
	{
		$this->connection = $connection;
		$this->tableName = $tableName;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getKey($providerName, $query)
	{
		return md5($providerName . $query);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cache(BatchGeocoded $geocoded)
	{
		$data = array_merge(
			array('id' => $this->getKey($geocoded->getProviderName(), $geocoded->getQuery())),
			$this->normalize($geocoded)
		);
		unset($data['coordinates']);
		$data['bounds_south'] = $data['bounds']['south'];
		$data['bounds_east'] = $data['bounds']['east'];
		$data['bounds_west'] = $data['bounds']['west'];
		$data['bounds_north'] = $data['bounds']['north'];
		unset($data['bounds']);
		try {
			$this->connection->insert($this->tableName, $data);
		} catch (\Exception $e) {
			throw new RuntimeException($e->getMessage());
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isCached($providerName, $query)
	{
		$result = $this->connection->fetchAssoc('SELECT * FROM ' . $this->tableName . ' WHERE id=?', array($this->getKey($providerName, $query)));

		if (!$result) {
			return false;
		}

		$result['coordinates'] = array($result['latitude'], $result['longitude']);
		$result['bounds'] = array(
			'south' => $result['bounds_south'],
			'west' => $result['bounds_west'],
			'north' => $result['bounds_north'],
			'east' => $result['bounds_east']
		);
		unset($result['bounds_south']);
		unset($result['bounds_west']);
		unset($result['bounds_north']);
		unset($result['bounds_east']);

		$cached = new BatchGeocoded();
		$cached->fromArray($result);

		return $cached;
	}

	/**
	 * {@inheritDoc}
	 */
	public function flush()
	{
		$this->connection->exec('TRUNCATE TABLE ' . $this->tableName);
	}

}