<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Contracts\ProductScraperInterface;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\SerpShoppingClient;

/**
 * Google Shopping adapter. Driver (config marketplaces.google_shopping.driver):
 * auto|serp|scrape. The SERP driver looks up the product id via a SerpApi-compatible
 * endpoint; with no api key it falls back to the HTML scrape path.
 */
final class GoogleShoppingAdapter extends AbstractApiAdapter
{
    public function __construct(
        ProductScraperInterface $scraper,
        private readonly SerpShoppingClient $serp,
    ) {
        parent::__construct($scraper);
    }

    public function code(): AdapterCode
    {
        return AdapterCode::GoogleShopping;
    }

    protected function configKey(): string
    {
        return 'google_shopping';
    }

    protected function externalRef(string $url): ?string
    {
        if (preg_match('#/shopping/product/(\d+)#', $url, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    protected function apiFetch(CompetitorProduct $competitorProduct, string $driver, array $options): ?ProductSnapshot
    {
        $id = $competitorProduct->external_ref ?? $this->externalRef($competitorProduct->url);
        if ($id === null) {
            return null;
        }

        return $this->serp->fetchByProductId($id)?->toSnapshot($competitorProduct->url);
    }
}
