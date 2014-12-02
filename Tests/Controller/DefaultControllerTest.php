<?php

namespace Keboola\GeocodingBundle\Tests\Controller;

use Syrup\ComponentBundle\Job\Metadata\Job;

class DefaultControllerTest extends AbstractControllerTest
{

    protected function runAugmentation($tableId, $column)
    {
        $job = $this->processJob('ag-geocoding/run', array(
            'tableId' => $tableId,
            'column' => $column
        ));
        $this->assertEquals(Job::STATUS_SUCCESS, $job->getStatus(), sprintf("Status of augmentation job should be success. Result:\n%s\n",
            json_encode($job->getResult())));
    }

    public function testIndex()
    {
        $this->runAugmentation('out.c-main.users', 'address');
    }
}
