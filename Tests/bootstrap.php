<?php
/**
 * @package forecastio-augmentation
 * @copyright 2014 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

defined('FORECASTIO_KEY') || define('FORECASTIO_KEY', getenv('FORECASTIO_KEY') ? getenv('FORECASTIO_KEY') : 'forecastio_api_key');
defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ? getenv('DB_HOST') : '127.0.0.1');
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ? getenv('DB_NAME') : 'ag_forecastio');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ? getenv('DB_USER') : 'user');
defined('DB_PASSWORD') || define('DB_PASSWORD', getenv('DB_PASSWORD') ? getenv('DB_PASSWORD') : '');
defined('DB_PORT') || define('DB_PORT', getenv('DB_PORT')? getenv('DB_PORT') : 3306);

require_once __DIR__ . '/../vendor/autoload.php';
