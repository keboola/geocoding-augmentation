<?php

use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

set_error_handler(
    function ($errno, $errstr, $errfile, $errline, array $errcontext) {
        if (0 === error_reporting()) {
            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
);

require_once(dirname(__FILE__) . "/../vendor/autoload.php");
$arguments = getopt("d::", array("data::"));
if (!isset($arguments['data'])) {
    print "Data folder not set.";
    exit(1);
}
$configFile = "{$arguments['data']}/config.json";
if (!file_exists($configFile)) {
    throw new \Exception("Config file not found at path $configFile");
}
$jsonDecode = new JsonDecode(true);
$config = $jsonDecode->decode(file_get_contents($configFile), JsonEncoder::FORMAT);

if (!file_exists("{$arguments['data']}/out")) {
    mkdir("{$arguments['data']}/out");
}
if (!file_exists("{$arguments['data']}/out/tables")) {
    mkdir("{$arguments['data']}/out/tables");
}

if (isset($config['parameters']['#apiKey'])) {
    $config['parameters']['apiKey'] = $config['parameters']['#apiKey'];
}
if (isset($config['parameters']['#privateKey'])) {
    $config['parameters']['privateKey'] = $config['parameters']['#privateKey'];
}

if ($config['parameters']['provider'] === 'openstreetmap') {
    // Backwards compatibility
    $config['parameters']['provider'] = 'nominatim';
}

try {
    \Keboola\GeocodingAugmentation\ParametersValidation::validate($config);

    $app = new \Keboola\GeocodingAugmentation\Augmentation(
        "{$arguments['data']}/out/tables/{$config['storage']['output']['tables'][0]['source']}",
        $config['storage']['output']['tables'][0]['destination'],
        $config['parameters']
    );

    foreach ($config['storage']['input']['tables'] as $table) {
        if (!file_exists("{$arguments['data']}/in/tables/{$table['destination']}")) {
            throw new Exception("File '{$table['destination']}' was not injected to the app");
        }

        \Keboola\GeocodingAugmentation\ParametersValidation::validateTable(
            $config['parameters']['method'],
            $table['destination'],
            "{$arguments['data']}/in/tables/{$table['destination']}"
        );

        $app->process($config['parameters']['method'], "{$arguments['data']}/in/tables/{$table['destination']}");
    }

    exit(0);
} catch (\Keboola\GeocodingAugmentation\Exception $e) {
    print sanitizeError($e->getMessage());
    exit(1);
} catch (\Exception $e) {
    print sanitizeError($e->getMessage());
    exit(2);
}

function sanitizeError($message)
{
    if (!empty($config['parameters']['apiKey'])) {
        $message = str_replace($message, $config['parameters']['apiKey'], '--apiKey--');
    }
    if (!empty($config['parameters']['privateKey'])) {
        $message = str_replace($message, $config['parameters']['privateKey'], '--privateKey--');
    }
    return $message;
}
