<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Discovery;

use Padosoft\LaravelAiSearchProviders\Data\SearchQueryData;

/**
 * Builds a SearchQueryData for competitor discovery, carrying country/locale in
 * the metadata bag so geo-aware providers (Brave country, Firecrawl location,
 * Tavily country) can consume them without an upstream contract change.
 */
final class GeoSearchQueryFactory
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public static function make(
        ?string $brand,
        ?string $model,
        ?string $ean = null,
        ?string $country = null,
        ?string $locale = null,
        ?string $site = null,
        int $limit = 10,
        array $extra = [],
    ): SearchQueryData {
        $metadata = array_filter([
            'country' => $country,
            'locale' => $locale,
        ], static fn ($v): bool => $v !== null && $v !== '');

        return SearchQueryData::fromArray([
            'brand' => $brand,
            'model' => $model,
            'ean' => $ean,
            'site' => $site,
            'limit' => $limit,
            'metadata' => array_merge($metadata, $extra),
        ]);
    }

    public static function country(SearchQueryData $query): ?string
    {
        $value = $query->metadata['country'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function locale(SearchQueryData $query): ?string
    {
        $value = $query->metadata['locale'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
