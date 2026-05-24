<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Contracts\MarketplaceAdapterInterface;
use Padosoft\PriceIntelligence\Contracts\ProductScraperInterface;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;

/**
 * Base marketplace adapter that delegates fetching to a ProductScraperInterface
 * (generic HTTP or Browsershot). Subclasses override extraction hooks for
 * marketplace-specific reference ids (ASIN, item id). Dedicated API drivers
 * (Amazon SP-API/Keepa, eBay API) can replace the scraper via DI without
 * changing the adapter contract.
 */
abstract class AbstractScrapeAdapter implements MarketplaceAdapterInterface
{
    public function __construct(
        protected readonly ProductScraperInterface $scraper,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function fetch(CompetitorProduct $competitorProduct, array $options = []): ProductSnapshot
    {
        $snapshot = $this->scraper->scrape($competitorProduct->url, $options);

        $ref = $this->externalRef($competitorProduct->url);

        if ($ref !== null && $competitorProduct->external_ref === null) {
            $competitorProduct->forceFill(['external_ref' => $ref])->save();
        }

        return $snapshot;
    }

    /**
     * Extract a marketplace-specific reference id from the URL (override).
     */
    protected function externalRef(string $url): ?string
    {
        return null;
    }
}
