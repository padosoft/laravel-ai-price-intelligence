<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Enums\AdapterCode;

/**
 * Amazon adapter. Default driver is scrape; SP-API / Keepa drivers can be wired
 * by rebinding ProductScraperInterface for amazon hosts. Extracts the ASIN.
 */
final class AmazonAdapter extends AbstractScrapeAdapter
{
    public function code(): AdapterCode
    {
        return AdapterCode::Amazon;
    }

    protected function externalRef(string $url): ?string
    {
        // /dp/ASIN , /gp/product/ASIN , /-/dp/ASIN
        if (preg_match('#/(?:dp|gp/product)/([A-Z0-9]{10})#i', $url, $m) === 1) {
            return strtoupper($m[1]);
        }

        return null;
    }
}
