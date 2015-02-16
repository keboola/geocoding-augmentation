<?php
/**
 * @package geocoding-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Tests;

use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Table;

abstract class AbstractTest extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{

    const APP_NAME = 'ag-geocoding';

    /**
     * @var StorageApiClient
     */
    protected $storageApiClient;

    protected $inBucket;
    protected $outBucket;
    protected $dataTableId;

    public function setUp()
    {
        $this->storageApiClient = new StorageApiClient(array(
            'token' => STORAGE_API_TOKEN,
            'url' => STORAGE_API_URL
        ));

        $this->inBucket = sprintf('in.c-%s', self::APP_NAME);
        $this->outBucket = sprintf('out.c-%s', self::APP_NAME);
        $this->dataTableId = sprintf('%s.%s', $this->outBucket, uniqid());

        // Cleanup
        if ($this->storageApiClient->bucketExists($this->inBucket)) {
            foreach ($this->storageApiClient->listTables($this->inBucket) as $table) {
                $this->storageApiClient->dropTable($table['id']);
            }
        }
        if ($this->storageApiClient->bucketExists($this->outBucket)) {
            foreach ($this->storageApiClient->listTables($this->outBucket) as $table) {
                $this->storageApiClient->dropTable($table['id']);
            }
        }

        if (!$this->storageApiClient->bucketExists($this->outBucket)) {
            $this->storageApiClient->createBucket(self::APP_NAME, 'out', 'Test');
        }

        // Prepare data table
        $t = new Table($this->storageApiClient, $this->dataTableId);
        $t->setHeader(array('addr', 'lat', 'lon'));
        $t->setFromArray(array(
            array('Praha', '35.235', '57.453'),
            array('Brno', '36.234', '56.443'),
            array('Ostrava', '35.235', '57.453'),
            array('Praha', '35.235', '57.553'),
            array('Plzeň', '35.333', '57.333'),
            array('Brno', '35.235', '57.453')
        ));
        $t->save();
    }
}
