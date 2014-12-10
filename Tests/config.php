<?php
/**
 * Tests configuration
 */

// Url to Storage API
if (!defined('STORAGE_API_URL'))
	define('STORAGE_API_URL', 'https://connection.keboola.com');

// Storage API token
if (!defined('STORAGE_API_TOKEN'))
	define('STORAGE_API_TOKEN', '395-11021-cea288fd90e46f5df6fb7158817a976b36ae1c3b');

// Google API key
if (!defined('GOOGLE_KEY'))
	define('GOOGLE_KEY', 'AIzaSyANAx315nk1QRh1HnCScgwu_YqoNBPYZSk');

// MapQuest API key
if (!defined('MAPQUEST_KEY'))
	define('MAPQUEST_KEY', 'Fmjtd%%7Cluur2h682g%%2C70%%3Do5-9w2wl0');

// DB host
if (!defined('DB_HOST'))
	define('DB_HOST', 'rds-devel-a.c97npkkbezqf.eu-west-1.rds.amazonaws.com');

// DB name
if (!defined('DB_NAME'))
	define('DB_NAME', 'jm_syrup');

// DB user
if (!defined('DB_USER'))
	define('DB_USER', 'jm_syrup');

// DB password
if (!defined('DB_PASSWORD'))
	define('DB_PASSWORD', 'Thwey1ret2nek9G');