<?php
/**
 * @package geocoding-augmentation
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

use Symfony\Component\Yaml\Yaml;

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
$config = Yaml::parse(file_get_contents("{$arguments['data']}/config.yml"));

\Keboola\GeocodingAugmentation\ParametersValidation::validate($config);

if (!file_exists("{$arguments['data']}/out")) {
    mkdir("{$arguments['data']}/out");
}
if (!file_exists("{$arguments['data']}/out/tables")) {
    mkdir("{$arguments['data']}/out/tables");
}

try {
    $app = new \Keboola\GeocodingAugmentation\Augmentation(
        "{$arguments['data']}/out/tables/{$config['parameters']['outputTable']}",
        $config['storage']['output']['tables'][0]['destination'],
        $config['parameters']
    );

    foreach ($config['parameters']['inputTables'] as $table) {
        if (!file_exists("{$arguments['data']}/in/tables/$table")) {
            throw new Exception("File '$table' was not injected to the app");
        }
        $manifest = Yaml::parse(
            file_get_contents("{$arguments['data']}/in/tables/$table.manifest"),
            true
        );

        \Keboola\GeocodingAugmentation\ParametersValidation::validateTable(
            $config['parameters']['method'],
            $table,
            $manifest
        );

        $app->process($config['parameters']['method'], "{$arguments['data']}/in/tables/$table");
    }

    exit(0);
} catch (\Keboola\GeocodingAugmentation\Exception $e) {
    print $e->getMessage();
    exit(1);
}
