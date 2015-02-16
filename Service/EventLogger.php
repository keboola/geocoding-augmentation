<?php
/**
 * @package geocoding-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Service;

use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Event as StorageApiEvent;

class EventLogger
{
    private $storageApiClient;
    private $jobId;

    const TYPE_INFO = StorageApiEvent::TYPE_INFO;
    const TYPE_ERROR = StorageApiEvent::TYPE_ERROR;
    const TYPE_SUCCESS = StorageApiEvent::TYPE_SUCCESS;
    const TYPE_WARN = StorageApiEvent::TYPE_WARN;

    public function __construct(StorageApiClient $storageApiClient, $jobId)
    {
        $this->storageApiClient = $storageApiClient;
        $this->jobId = $jobId;
    }

    public function log($message, $params = array(), $duration = null, $type = self::TYPE_INFO)
    {
        $event = new StorageApiEvent();
        $event
            ->setType($type)
            ->setMessage($message)
            ->setComponent('ag-geocoding') //@TODO load from config
            ->setConfigurationId($this->jobId)
            ->setRunId($this->storageApiClient->getRunId());
        if (count($params)) {
            $event->setParams($params);
        }
        if ($duration) {
            $event->setDuration($duration);
        }
        $this->storageApiClient->createEvent($event);
    }
}
