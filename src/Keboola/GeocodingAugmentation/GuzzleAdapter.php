<?php
/**
 * @package geocoding-augmentation
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GeocodingAugmentation;
use Geocoder\HttpAdapter\HttpAdapterInterface;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleAdapter implements HttpAdapterInterface
{
    /**
     * @var Client
     */
    protected $client;
    /**
     * @param Client $client Client object
     */
    public function __construct(Client $client = null)
    {
        if (!$client) {
            $handlerStack = HandlerStack::create();
            /** @noinspection PhpUnusedParameterInspection */
            $handlerStack->push(Middleware::retry(
                function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                    return $retries <= 10;
                },
                function ($retries) {
                    return (int) pow(2, $retries - 1) * 1000;
                }
            ));
            $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);
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