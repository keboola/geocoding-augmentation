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
    private $providers = [];
    private $providersLog = [];

    /**
     * Constructor
     *
     * @param ProviderInterface[] $providers
     */
    public function __construct(array $providers = [])
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
                $result = $provider->getGeocodedData($address);
                $this->providersLog[$address] = $provider->getName();
                return $result;
            } catch (InvalidCredentialsException $e) {
                throw $e;
            } catch (\Exception $e) {
                $exceptions[] = $e;
            }
        }

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
                $result = $provider->getReversedData($coordinates);
                $this->providersLog[sprintf('%F,%F', $coordinates[0], $coordinates[1])] = $provider->getName();
                return $result;
            } catch (InvalidCredentialsException $e) {
                throw $e;
            } catch (\Exception $e) {
                $exceptions[] = $e;
            }
        }

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
        return 'chain';
    }

    public function getProvidersLog()
    {
        return $this->providersLog;
    }
}
