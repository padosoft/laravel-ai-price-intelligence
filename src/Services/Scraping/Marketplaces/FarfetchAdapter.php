<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Contracts\ProductScraperInterface;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\FarfetchClient;

/**
 * Farfetch luxury-marketplace adapter. Driver (config marketplaces.farfetch.driver):
 * scrape (default, JSON-LD via the HTML scraper) | retailed | apify (commercial APIs).
 * Missing keys for the commercial drivers fall back to scraping.
 */
final class FarfetchAdapter extends AbstractApiAdapter
{
    public function __construct(
        ProductScraperInterface $scraper,
        private readonly FarfetchClient $client,
    ) {
        parent::__construct($scraper);
    }

    public function code(): AdapterCode
    {
        return AdapterCode::Farfetch;
    }

    protected function configKey(): string
    {
        return 'farfetch';
    }

    protected function externalRef(string $url): ?string
    {
        // .../gucci-logo-tshirt-item-21380995.aspx
        if (preg_match('#item-(\d{5,})\.aspx#i', $url, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    protected function apiFetch(CompetitorProduct $competitorProduct, string $driver, array $options): ?ProductSnapshot
    {
        $result = match ($driver) {
            'retailed' => $this->client->fetchViaRetailed($competitorProduct->url),
            'apify' => $this->client->fetchViaApify($competitorProduct->url),
            default => null,
        };

        return $result?->toSnapshot($competitorProduct->url);
    }
}
