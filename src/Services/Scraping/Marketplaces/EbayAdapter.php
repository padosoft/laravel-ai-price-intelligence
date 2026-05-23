<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Enums\AdapterCode;

final class EbayAdapter extends AbstractScrapeAdapter
{
    public function code(): AdapterCode
    {
        return AdapterCode::Ebay;
    }

    protected function externalRef(string $url): ?string
    {
        // /itm/123456789012 or /itm/title/123456789012
        if (preg_match('#/itm/(?:[^/]+/)?(\d{9,15})#', $url, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
