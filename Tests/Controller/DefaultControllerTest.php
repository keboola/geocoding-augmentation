<?php

namespace Keboola\GeocodingBundle\Tests\Controller;

class DefaultControllerTest extends AbstractControllerTest
{

    protected function runAugmentation($tableId, $column)
    {
        $this->processJob('ag-geocoding/run', array(
            'tableId' => $tableId,
            'column' => $column
        ));
    }

    public function testIndex()
    {
        $this->runAugmentation('out.c-main.users', 'address');
    }
}
