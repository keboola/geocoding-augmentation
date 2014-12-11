<?php
/**
 * @package geocoding-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Geocoder;

use Geocoder\HttpAdapter\HttpAdapterInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Retry\RetrySubscriber;

class GuzzleAdapter implements HttpAdapterInterface
{
	/**
	 * @var Client
	 */
	protected $client;

	/**
	 * @param Client $client Client object
	 */
	public function __construct(Client $client=null)
	{
		if (!$client) {
			$client = new Client();
			$retry = new RetrySubscriber([
				'filter' => RetrySubscriber::createChainFilter(array(
						RetrySubscriber::createCurlFilter(),
						RetrySubscriber::createStatusFilter()
					))
			]);
			$client->getEmitter()->attach($retry);
		}
		$this->client = $client;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getContent($url)
	{
		$response = $this->client->get($url);
		return (string) $response->getBody();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName()
	{
		return 'guzzle';
	}
} 