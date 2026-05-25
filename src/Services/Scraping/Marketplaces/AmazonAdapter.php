<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Contracts\ProductScraperInterface;
use Padosoft\PriceIntelligence\Data\ApiProductResult;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\AmazonSpApiClient;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\KeepaClient;

/**
 * Amazon adapter. Driver (config marketplaces.amazon.driver): auto|sp_api|keepa|scrape.
 * API drivers map an ASIN to a ProductSnapshot via SP-API pricing or Keepa; with no
 * credentials (or driver=scrape) it falls back to the HTML scrape path.
 */
final class AmazonAdapter extends AbstractApiAdapter
{
    public function __construct(
        ProductScraperInterface $scraper,
        private readonly AmazonSpApiClient $spApi,
        private readonly KeepaClient $keepa,
    ) {
        parent::__construct($scraper);
    }

    public function code(): AdapterCode
    {
        return AdapterCode::Amazon;
    }

    protected function configKey(): string
    {
        return 'amazon';
    }

    protected function externalRef(string $url): ?string
    {
        // /dp/ASIN , /gp/product/ASIN , /-/dp/ASIN
        if (preg_match('#/(?:dp|gp/product)/([A-Z0-9]{10})#i', $url, $m) === 1) {
            return strtoupper($m[1]);
        }

        return null;
    }

    protected function apiFetch(CompetitorProduct $competitorProduct, string $driver, array $options): ?ProductSnapshot
    {
        $asin = $competitorProduct->external_ref ?? $this->externalRef($competitorProduct->url);
        if ($asin === null) {
            return null;
        }

        $result = match ($driver) {
            'sp_api' => $this->spApi->fetchOffers($asin),
            'keepa' => $this->keepa->fetchByAsin($asin),
            default => $this->autoFetch($asin),
        };

        return $result?->toSnapshot($competitorProduct->url);
    }

    /**
     * Try SP-API first; if it returns no price data (zero offers), fall through to Keepa.
     * If both succeed but only one has prices, prefer the priced result.
     */
    private function autoFetch(string $asin): ?ApiProductResult
    {
        $spResult = $this->spApi->fetchOffers($asin);

        if ($spResult !== null && $spResult->priceCents !== null) {
            return $spResult;
        }

        // SP-API null (no creds) or returned zero offers — try Keepa.
        $keepaResult = $this->keepa->fetchByAsin($asin);

        // Prefer whichever has pricing; fall back to SP-API result (at least has offerCount).
        return $keepaResult ?? $spResult;
    }
}
