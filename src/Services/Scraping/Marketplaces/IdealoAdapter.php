<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Enums\AdapterCode;

final class IdealoAdapter extends AbstractScrapeAdapter
{
    public function code(): AdapterCode
    {
        return AdapterCode::Idealo;
    }

    protected function externalRef(string $url): ?string
    {
        // idealo product pages: /preisvergleich/OffersOfProduct/123456_...
        if (preg_match('#/(?:OffersOfProduct|preisvergleich/OffersOfProduct)/(\d+)#', $url, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
