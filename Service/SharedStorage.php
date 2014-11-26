<?php
/**
 * Created by IntelliJ IDEA.
 * User: JakubM
 * Date: 04.09.14
 * Time: 14:32
 */

namespace Keboola\GeocodingBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;

class SharedStorage
{
	/**
	 * @var \Doctrine\DBAL\Connection
	 */
	protected $db;

	public function __construct(\Doctrine\Bundle\DoctrineBundle\Registry $doctrine)
	{
		$this->db = $doctrine->getConnection();
	}

	public function getSavedLocations($locations)
	{
		$query = $this->db->fetchAll('SELECT name,latitude,longitude FROM locations WHERE name IN (?)', array($locations), array(Connection::PARAM_STR_ARRAY));
		$result = array();
		foreach ($query as $q) {
			$error = $q['latitude'] == 0 && $q['longitude'] == 0;
			$result[$q['name']] = array(
				'latitude' => $error? '-' : $q['latitude'],
				'longitude' => $error? '-' : $q['longitude']
			);
		}
		return $result;
	}

	public function saveLocation($name, $lat, $lon, $provider, $exception)
	{
		try {
			$this->db->insert('locations', array(
				'name' => $name,
				'latitude' => $lat,
				'longitude' => $lon,
				'provider' => $provider,
				'error' => $exception
			));
		} catch (DBALException $e) {
			// Ignore
		}
	}
} 