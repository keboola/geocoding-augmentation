<?php
/**
 * @package geocoding-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\GeocodingAugmentation;

use Keboola\GeocodingAugmentation\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class KeboolaGeocodingAugmentation extends Bundle
{

	public function getContainerExtension()
	{
		return new Extension();
	}

}
