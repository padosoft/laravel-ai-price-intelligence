<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Enums\AdapterCode;

final class GoogleShoppingAdapter extends AbstractScrapeAdapter
{
    public function code(): AdapterCode
    {
        return AdapterCode::GoogleShopping;
    }

    protected function externalRef(string $url): ?string
    {
        if (preg_match('#/shopping/product/(\d+)#', $url, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
