geocoding-augmentation
================

KBC Docker app for geocoding of locations to gps coordinates or reverse
 geocoding of coordinates to locations. You specify from which provider 
 we get the data and you have to obtain access to the service.
 
 [User Documentation](https://help.keboola.com/extractors/geocoding-augumentation/)

## Status

[![Build Status](https://travis-ci.org/keboola/geocoding-augmentation.svg)](https://travis-ci.org/keboola/geocoding-augmentation) [![Code Climate](https://codeclimate.com/github/keboola/geocoding-augmentation/badges/gpa.svg)](https://codeclimate.com/github/keboola/geocoding-augmentation) [![Test Coverage](https://codeclimate.com/github/keboola/geocoding-augmentation/badges/coverage.svg)](https://codeclimate.com/github/keboola/geocoding-augmentation/coverage)

# Source data
- source tables must contain exactly one column with locales in case of forward geocoding or exactly two columns in case of reverse geocoding
- you should deduplicate your data to avoid useless exhausting of your API key quota
- latitudes and longitudes have to be decimal degrees


## Configuration

- **parameters**
    - **method** - method of geocoding, allowed values are:
        - **geocode** - standard geocoding of locations to coordinates
        - **reverse** - reverse geocoding of coordinates to locations
    - **provider** - name of provider which will be queried for the data, allowed values are:
        - **google_maps** - Google Maps provider, needs parameter **apiKey** with your access key to the API (you need "Server" type of key)
        - **google_maps_business** - Google Maps for Business provider, needs parameters **clientId** and **privateKey**
        - **bing_maps** - Bing Maps provider, needs attribute **apiKey**
        - **yandex** - Yandex provider, does not need any API key, locale parameter may be one of these values: uk-UA, be-BY, en-US, en-BR, tr-TR
        - **map_quest** - MapQuest provider, needs parameter **apiKey**
        - **tomtom** - TomTom provider, needs parameter **apiKey**, parameter locale may have one of these values: de, es, fr, it, nl, pl, pt, sv
        - **opencage**: OpenCage provider, needs parameter **apiKey**
        - **openstreetmap**: OpenStreetMap provider, does not need API key
    - **locale** - code of language used for local names 
 
Parameters **apiKey** and **privateKey** can be encrypted and it is recommended to encrypt them.
 
Example:
```
{
    "parameters": {
        "method": "reverse",
        "provider": "google_maps",
        "#apiKey": "jfdksjknvmcxmvnc,x",
        "locale": "de"
    }
}
```
      
# Output
The app will save data to single table filled incrementally with following columns: 

- **primary**: primary key used for incremental loads (is md5 hash of query, provider and locale separated by colons)
- **query**: query used for geocoding (location or coordinates)
- **provider**: name of used data provider
- **locale**: locale of the data
- **latitude**: latitude of the location
- **longitude**: longitude of the location
- **streetNumber**: street number of the location
- **streetName**: street name of the location
- **zipcode**: zip code of the location
- **city**: city of the location
- **cityDistrict**: city district of the location
- **county**: county of the location
- **countyCode**: county code of the location
- **region**: region of the location
- **regionCode**: region code of the location
- **country**: country of the location
- **countryCode**: country code of the location
- **bounds_south**: south bound of the location
- **bounds_east**: east bound of the location
- **bounds_west**: west bound of the location
- **bounds_north**: north bound of the location
- **timezone**: timezone of the location

Please note that some providers does not provide all data but just some of them.
