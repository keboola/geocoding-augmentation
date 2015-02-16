<?php
namespace Keboola\GeocodingAugmentation\Service;

use Keboola\Syrup\Exception\SyrupComponentException;

class ConfigurationException extends SyrupComponentException
{
    public function __construct($message, $previous = null)
    {
        parent::__construct(400, $message, $previous);
    }
}
