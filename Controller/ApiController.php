<?php
/**
 * @package geocoding-bundle
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Syrup\ComponentBundle\Exception\ApplicationException;
use Syrup\ComponentBundle\Exception\UserException;
use Syrup\ComponentBundle\Job\Metadata\Job;

class ApiController extends \Syrup\ComponentBundle\Controller\ApiController
{


    /**
     * Geocoding
     *
     * @Route("/geocode")
     * @Method({"POST"})
     */
    public function geocodeAction(Request $request)
    {
        $params = $this->getPostJson($request);
        $this->checkRequiredParams($params, array('tableId', 'location'));
        $this->checkMappingParams($params);
        return $this->enqueueJob('geocode', $params);
    }

    /**
     * Reverse geocoding
     *
     * @Route("/reverse")
     * @Method({"POST"})
     */
    public function reverseAction(Request $request)
    {
        $params = $this->getPostJson($request);
        $this->checkRequiredParams($params, array('tableId', 'latitude', 'longitude'));
        $this->checkMappingParams($params);
        return $this->enqueueJob('reverse', $params);
    }


    private function checkRequiredParams($params, $required)
    {
        foreach ($required as $r) {
            if (!isset($params[$r])) {
                throw new UserException(sprintf("Parameter '%s' is required", $r));
            }
        }
    }

    private function enqueueJob($command, $params)
    {
        // Create new job
        /** @var Job $job */
        $job = $this->createJob($command, $params);

        // Add job to Elasticsearch
        try {
            $jobId = $this->getJobManager()->indexJob($job);
        } catch (\Exception $e) {
            throw new ApplicationException("Failed to create job", $e);
        }

        // Add job to SQS
        $queueName = 'default';
        $queueParams = $this->container->getParameter('queue');
        if (isset($queueParams['sqs'])) {
            $queueName = $queueParams['sqs'];
        }
        $this->enqueue($jobId, $queueName);

        // Response with link to job resource
        return $this->createJsonResponse([
            'id'        => $jobId,
            'url'       => $this->getJobUrl($jobId),
            'status'    => $job->getStatus()
        ], 202);
    }
}
