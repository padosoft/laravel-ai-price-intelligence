<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\ProductSnapshot;

/**
 * Fetches and extracts a normalized ProductSnapshot from a known competitor URL.
 * Implementations: SearchProviderScrapeDriver (Firecrawl/Exa/Tavily via
 * laravel-ai-search-providers), BrowsershotScrapeDriver, GenericHttpDriver.
 */
interface ProductScraperInterface
{
    /**
     * @param  array<string, mixed>  $options  e.g. ['country' => 'IT', 'locale' => 'it-IT']
     */
    public function scrape(string $url, array $options = []): ProductSnapshot;

    public function supports(string $url): bool;
}
