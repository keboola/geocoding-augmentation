<?php
/**
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2014-12-02
 */
namespace Keboola\GeocodingBundle\Tests\Controller;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase,
	Symfony\Bundle\FrameworkBundle\Console\Application,
	Symfony\Component\Console\Tester\CommandTester;
use Keboola\StorageApi\Client as StorageApiClient;
use Syrup\ComponentBundle\Command\JobCommand;
use Syrup\ComponentBundle\Job\Metadata\Job;
use Syrup\ComponentBundle\Job\Metadata\JobManager;

abstract class AbstractControllerTest extends WebTestCase
{
	protected $storageApiToken;
	/**
	 * @var \Keboola\StorageApi\Client
	 */
	protected $storageApiClient;
	/**
	 * @var \Symfony\Bundle\FrameworkBundle\Client
	 */
	protected $httpClient;
	/**
	 * @var CommandTester
	 */
	protected $commandTester;
	/**
	 * @var JobManager
	 */
	protected $jobManager;


	/**
	 * Setup HTTP client, command runner and Storage API client for each test
	 */
	protected function setUp()
	{
		$this->httpClient = static::createClient();
		$container = $this->httpClient->getContainer();

		if (!$this->storageApiToken)
			$this->storageApiToken = $container->getParameter('storage_api.test.token');

		$this->httpClient->setServerParameters(array(
			'HTTP_X-StorageApi-Token' => $this->storageApiToken
		));

		$this->jobManager = $container->get('syrup.job_manager');

		$application = new Application($this->httpClient->getKernel());
		$application->add(new JobCommand());
		$command = $application->find('syrup:run-job');
		$this->commandTester = new CommandTester($command);

		$this->storageApiClient = new StorageApiClient(array(
			'token' => $this->storageApiToken,
			'url' => $container->getParameter('storage_api.test.url'))
		);

		/** To make annotations work here */
		AnnotationRegistry::registerAutoloadNamespaces(array(
			'Sensio\\Bundle\\FrameworkExtraBundle' => '../../vendor/sensio/framework-extra-bundle/'
		));
	}

	/**
	 * Request to API, return result
	 * @param string $url URL of API call
	 * @param string $method HTTP method of API call
	 * @param array $params parameters of POST call
	 * @return array
	 */
	protected function callApi($url, $method='POST', $params=array())
	{
		$this->httpClient->request($method, $url, array(), array(), array(), json_encode($params));
		$response = $this->httpClient->getResponse();
		/* @var \Symfony\Component\HttpFoundation\Response $response */

		$responseJson = json_decode($response->getContent(), true);
		$this->assertNotEmpty($responseJson, sprintf("Response of API call '%s' should not be empty.", $url));

		return $responseJson;
	}


	/**
	 * Call API and process job immediately, return job info
	 * @param string $url URL of API call
	 * @param array $params parameters of POST call
	 * @param string $method HTTP method of API call
	 * @return Job
	 */
	protected function processJob($url, $params=array(), $method='POST')
	{
		$responseJson = $this->callApi($url, $method, $params);
		$this->assertArrayHasKey('id', $responseJson, sprintf("Response of API call '%s' should contain 'id' key. Result is:\n%s\n",
			$url, json_encode($responseJson)));
		$this->commandTester->execute(array(
			'command' => 'syrup:run-job',
			'jobId' => $responseJson['id']
		));

		return $this->jobManager->getJob($responseJson['id']);
	}

}
