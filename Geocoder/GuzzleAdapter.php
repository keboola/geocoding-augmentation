<?php
/**
 * Created by IntelliJ IDEA.
 * User: JakubM
 * Date: 08.09.14
 * Time: 11:48
 */

namespace Keboola\GeocodingBundle\Geocoder;

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