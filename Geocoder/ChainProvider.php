<?php
/**
 * @package ag-geocoding
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Geocoder;
use Geocoder\Exception\InvalidCredentialsException;
use Geocoder\Exception\ChainNoResultException;
use Geocoder\Provider\ProviderInterface;

/**
 * Based on \Geocoder\Provider\ChainProvider
 * By Markus Bachmann <markus.bachmann@bachi.biz>
 */
class ChainProvider implements ProviderInterface
{
	/**
	 * @var ProviderInterface[]
	 */
	private $providers = array();
	private $currentProvider;

	/**
	 * Constructor
	 *
	 * @param ProviderInterface[] $providers
	 */
	public function __construct(array $providers = array())
	{
		$this->providers = $providers;
	}

	/**
	 * Add a provider
	 *
	 * @param ProviderInterface $provider
	 */
	public function addProvider(ProviderInterface $provider)
	{
		$this->providers[] = $provider;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getGeocodedData($address)
	{
		$exceptions = array();

		foreach ($this->providers as $provider) {
			try {
				$this->currentProvider = $provider->getName();
				return $provider->getGeocodedData($address);
			} catch (InvalidCredentialsException $e) {
				throw $e;
			} catch (\Exception $e) {
				$exceptions[] = $e;
			}
		}

		$this->currentProvider = null;
		throw new ChainNoResultException(sprintf('No provider could provide the address "%s"', $address), $exceptions);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReversedData(array $coordinates)
	{
		$exceptions = array();

		foreach ($this->providers as $provider) {
			try {
				$this->currentProvider = $provider->getName();
				return $provider->getReversedData($coordinates);
			} catch (InvalidCredentialsException $e) {
				throw $e;
			} catch (\Exception $e) {
				$exceptions[] = $e;
			}
		}

		$this->currentProvider = null;
		throw new ChainNoResultException(sprintf('No provider could provide the coordinated %s', json_encode($coordinates)), $exceptions);
	}

	/**
	 * {@inheritDoc}
	 */
	public function setMaxResults($limit)
	{
		foreach ($this->providers as $provider) {
			$provider->setMaxResults($limit);
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName()
	{
		return $this->currentProvider;
	}
}