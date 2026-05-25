<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Throwable;

/**
 * Base for marketplaces that have a real API path with graceful scrape fallback.
 * Subclasses pick a driver from config and implement apiFetch(); when it returns null
 * (driver=scrape, missing creds, or a failed/empty API call) the inherited scrape path runs.
 */
abstract class AbstractApiAdapter extends AbstractScrapeAdapter
{
    /** Config key under price-intelligence.marketplaces (e.g. 'amazon'). */
    abstract protected function configKey(): string;

    /**
     * Try the configured API driver. Return null to fall back to scraping.
     *
     * @param  array<string, mixed>  $options
     */
    abstract protected function apiFetch(CompetitorProduct $competitorProduct, string $driver, array $options): ?ProductSnapshot;

    /**
     * @param  array<string, mixed>  $options
     */
    public function fetch(CompetitorProduct $competitorProduct, array $options = []): ProductSnapshot
    {
        $driver = (string) config('price-intelligence.marketplaces.'.$this->configKey().'.driver', 'scrape');

        if ($driver !== 'scrape') {
            try {
                $snapshot = $this->apiFetch($competitorProduct, $driver, $options);
            } catch (Throwable $e) {
                if (function_exists('report')) {
                    report($e);
                }
                $snapshot = null;
            }

            // Accept the API result only if it carries a usable signal: a price, or an explicit
            // "unavailable" verdict (out of stock). A reachable-but-empty response (price null AND
            // still "available") is treated as a miss and falls through to scraping a parsable page.
            if ($snapshot !== null && $snapshot->reachable && ($snapshot->priceCents !== null || ! $snapshot->available)) {
                $ref = $this->externalRef($competitorProduct->url) ?? $snapshot->externalRef;
                if ($ref !== null && $competitorProduct->external_ref === null) {
                    $competitorProduct->forceFill(['external_ref' => $ref])->save();
                }

                return $snapshot;
            }
        }

        // scrape fallback (parent also persists external_ref)
        return parent::fetch($competitorProduct, $options);
    }
}
