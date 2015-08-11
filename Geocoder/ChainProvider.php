<?php
/**
 * @package ag-geocoding
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation\Geocoder;

class ChainProvider extends \Geocoder\Provider\ChainProvider
{
    public function getName()
    {
        return 'default';
    }
}
