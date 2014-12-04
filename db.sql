CREATE TABLE `geotools_cache` (
  `id` varchar(128) NOT NULL DEFAULT '',
  `providerName` varchar(20) NOT NULL DEFAULT '',
  `query` varchar(128) NOT NULL DEFAULT '',
  `exceptionMessage` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `bounds_north` decimal(10,7) DEFAULT NULL,
  `bounds_east` decimal(10,7) DEFAULT NULL,
  `bounds_south` decimal(10,7) DEFAULT NULL,
  `bounds_west` decimal(10,7) DEFAULT NULL,
  `streetNumber` int(10) unsigned DEFAULT NULL,
  `streetName` varchar(128) DEFAULT NULL,
  `city` varchar(128) DEFAULT NULL,
  `zipcode` varchar(20) DEFAULT NULL,
  `cityDistrict` varchar(128) DEFAULT NULL,
  `county` varchar(128) DEFAULT NULL,
  `countyCode` varchar(128) DEFAULT NULL,
  `region` varchar(128) DEFAULT NULL,
  `regionCode` varchar(128) DEFAULT NULL,
  `country` varchar(128) DEFAULT NULL,
  `countryCode` varchar(10) DEFAULT NULL,
  `timezone` varchar(10) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;