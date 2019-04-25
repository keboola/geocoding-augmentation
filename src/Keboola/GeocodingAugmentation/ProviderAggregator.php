<?php
declare(strict_types=1);

/*
 * This is an interface between providers for Geocoder 4 and Geotools
 * because \League\Geotools\Geotools::batch() does not accept Geocoder\Provider interface
 * which is used by Geocoder 4 but accepts only Geocoder\Geocoder
 */

namespace Keboola\GeocodingAugmentation;

use Geocoder\Collection;
use Geocoder\Geocoder;
use Geocoder\Model\Coordinates;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Provider\Provider;

class ProviderAggregator extends \Geocoder\ProviderAggregator
{
    /**
     * @var Provider
     */
    private $provider;

    /**
     * @var string
     */
    private $locale;

    public function __construct(Provider $provider, string $locale)
    {
        $this->provider = $provider;
        $this->locale = $locale;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $query = $query->withLocale($this->locale);
        return $this->provider->geocodeQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseQuery(ReverseQuery $query): Collection
    {
        $query = $query->withLocale($this->locale);
        return $this->provider->reverseQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->provider->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function geocode(string $value): Collection
    {
        return $this->geocodeQuery(GeocodeQuery::create($value)
            ->withLimit(Geocoder::DEFAULT_RESULT_LIMIT));
    }

    /**
     * {@inheritdoc}
     */
    public function reverse(float $latitude, float $longitude): Collection
    {
        return $this->reverseQuery(ReverseQuery::create(new Coordinates($latitude, $longitude))
            ->withLimit(Geocoder::DEFAULT_RESULT_LIMIT));
    }

    /**
     * {@inheritdoc}
     */
    public function getProviders(): array
    {
        return [$this->provider];
    }

    /**
     * Ignore, we use just the one provider
     *
     * @param string $name
     *
     * @return ProviderAggregator
     */
    public function using(string $name): \Geocoder\ProviderAggregator
    {
        return $this;
    }
}
