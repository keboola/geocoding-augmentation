<?php
/**
 * @package geocoding-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Tests;

use Keboola\GeocodingAugmentation\JobExecutor;
use Keboola\GeocodingAugmentation\Service\ConfigurationStorage;
use Keboola\GeocodingAugmentation\Service\SharedStorage;
use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Table;
use Keboola\Syrup\Job\Metadata\Job;

class FunctionalTest extends AbstractTest
{
    /**
     * @var JobExecutor
     */
    private $jobExecutor;

    public function setUp()
    {
        parent::setUp();

        $db = \Doctrine\DBAL\DriverManager::getConnection(array(
            'driver' => 'pdo_mysql',
            'host' => DB_HOST,
            'dbname' => DB_NAME,
            'user' => DB_USER,
            'password' => DB_PASSWORD,
        ));

        $stmt = $db->prepare(file_get_contents(__DIR__ . '/../db.sql'));
        $stmt->execute();

        $sharedStorage = new SharedStorage($db);

        $temp = new \Keboola\Temp\Temp(self::APP_NAME);

        $this->jobExecutor = new JobExecutor($sharedStorage, $temp, GOOGLE_KEY, MAPQUEST_KEY);
        $this->jobExecutor->setStorageApi($this->storageApiClient);
    }

    public function testForward()
    {
        // Cleanup
        $configId = 'forward';
        $configTableId = sprintf('%s.%s', ConfigurationStorage::BUCKET_ID, $configId);
        if ($this->storageApiClient->tableExists($configTableId)) {
            $this->storageApiClient->dropTable($configTableId);
        }

        // Init
        list($bucketStage, $bucketName) = explode('.', ConfigurationStorage::BUCKET_ID);
        if (!$this->storageApiClient->bucketExists(ConfigurationStorage::BUCKET_ID)) {
            $this->storageApiClient->createBucket(substr($bucketName, 2), $bucketStage, 'Geocoding config');
        }

        $t = new Table($this->storageApiClient, $configTableId);
        $t->setHeader(array('tableId', 'addressCol'));
        $t->setAttribute('method', 'geocode');
        $t->setFromArray(array(
            array($this->dataTableId, 'addr')
        ));
        $t->save();

        // Test
        $this->jobExecutor->execute(new Job(array(
            'id' => uniqid(),
            'runId' => uniqid(),
            'token' => $this->storageApiClient->getLogData(),
            'component' => self::APP_NAME,
            'command' => 'run',
            'params' => array(
                'config' => $configId
            )
        )));

        $this->assertTrue($this->storageApiClient->tableExists(sprintf('%s.%s', $this->inBucket, $configId)));
        $export = $this->storageApiClient->exportTable(sprintf('%s.%s', $this->inBucket, $configId));
        $csv = StorageApiClient::parseCsv($export, true);
        $this->assertEquals(4, count($csv));
    }

    public function testReverse()
    {
        // Cleanup
        $configId = 'reverse';
        $configTableId = sprintf('%s.%s', ConfigurationStorage::BUCKET_ID, $configId);
        if ($this->storageApiClient->tableExists($configTableId)) {
            $this->storageApiClient->dropTable($configTableId);
        }

        // Init
        list($bucketStage, $bucketName) = explode('.', ConfigurationStorage::BUCKET_ID);
        if (!$this->storageApiClient->bucketExists(ConfigurationStorage::BUCKET_ID)) {
            $this->storageApiClient->createBucket(substr($bucketName, 2), $bucketStage, 'Geocoding config');
        }

        $t = new Table($this->storageApiClient, $configTableId);
        $t->setHeader(array('tableId', 'latitudeCol', 'longitudeCol'));
        $t->setAttribute('method', 'reverse');
        $t->setFromArray(array(
            array($this->dataTableId, 'lat', 'lon')
        ));
        $t->save();

        // Test
        $this->jobExecutor->execute(new Job(array(
            'id' => uniqid(),
            'runId' => uniqid(),
            'token' => $this->storageApiClient->getLogData(),
            'component' => self::APP_NAME,
            'command' => 'run',
            'params' => array(
                'config' => $configId
            )
        )));

        $this->assertTrue($this->storageApiClient->tableExists(sprintf('%s.%s', $this->inBucket, $configId)));
        $export = $this->storageApiClient->exportTable(sprintf('%s.%s', $this->inBucket, $configId));
        $csv = StorageApiClient::parseCsv($export, true);
        $this->assertEquals(4, count($csv));
    }
}
