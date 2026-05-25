<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Contracts\ProductScraperInterface;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\EbayBrowseClient;

/**
 * eBay adapter. Driver (config marketplaces.ebay.driver): auto|api|scrape. The API
 * driver resolves the legacy item id via the Browse API; with no credentials it
 * falls back to the HTML scrape path.
 */
final class EbayAdapter extends AbstractApiAdapter
{
    public function __construct(
        ProductScraperInterface $scraper,
        private readonly EbayBrowseClient $browse,
    ) {
        parent::__construct($scraper);
    }

    public function code(): AdapterCode
    {
        return AdapterCode::Ebay;
    }

    protected function configKey(): string
    {
        return 'ebay';
    }

    protected function externalRef(string $url): ?string
    {
        // /itm/123456789012 or /itm/title/123456789012
        if (preg_match('#/itm/(?:[^/]+/)?(\d{9,15})#', $url, $m) === 1) {
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

        return $this->browse->fetchByLegacyId($id)?->toSnapshot($competitorProduct->url);
    }
}
