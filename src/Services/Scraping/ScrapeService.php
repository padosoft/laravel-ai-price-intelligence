<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping;

use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\ContentSnapshot;
use Padosoft\PriceIntelligence\Models\FetchLog;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\PromoObservation;
use Padosoft\PriceIntelligence\Models\StockObservation;
use Padosoft\PriceIntelligence\Services\Pricing\PriceNormalizer;

/**
 * Fetches a competitor product, normalizes its price and persists the resulting
 * observations + an audit FetchLog. Returns the snapshot so callers (jobs) can
 * diff it and emit events. Unreachable fetches are logged but produce no price
 * observation (the URL stays valid for the next cycle).
 */
final class ScrapeService
{
    public function __construct(
        private readonly MarketplaceAdapterFactory $adapters,
        private readonly PriceNormalizer $normalizer,
        private readonly \Padosoft\PriceIntelligence\Services\Alerts\AlertDispatcher $alerts,
        private readonly \Padosoft\PriceIntelligence\Contracts\PiiFilterInterface $pii,
    ) {
    }

    private function redact(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        // GDPR: strip any PII captured in scraped content before persisting.
        // pii.enabled is 'auto'|bool: 'auto' means on; otherwise interpret robustly so
        // 'false'/'0'/0 all disable (not just a strict boolean false).
        $enabled = config('price-intelligence.pii.enabled', 'auto');
        $on = $enabled === 'auto' ? true : filter_var($enabled, FILTER_VALIDATE_BOOLEAN);

        return $on ? $this->pii->redact($text) : $text;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function scrapeAndStore(CompetitorProduct $competitor, array $options = []): ProductSnapshot
    {
        $code = $competitor->source?->adapter_code ?? AdapterCode::Generic;
        $adapter = $this->adapters->make($code);

        $previous = PriceObservation::query()
            ->where('competitor_product_id', $competitor->id)
            ->latest('captured_at')
            ->first();

        $startedAt = microtime(true);
        $snapshot = $adapter->fetch($competitor, $options);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $fetchLog = FetchLog::query()->create([
            'tenant_id' => $competitor->tenant_id,
            'competitor_source_id' => $competitor->competitor_source_id,
            'url' => $competitor->url,
            'method' => 'GET',
            'status' => $snapshot->httpStatus,
            'latency_ms' => $latencyMs,
            'driver' => $code->value,
            'body_hash' => $snapshot->htmlHash,
            'robots_allowed' => true,
            'captured_at' => now(),
        ]);

        if (! $snapshot->reachable) {
            return $snapshot;
        }

        $normalized = $this->normalizer->normalize($snapshot);
        $now = now();

        if ($normalized['price_cents'] !== null) {
            PriceObservation::query()->create([
                'tenant_id' => $competitor->tenant_id,
                'competitor_product_id' => $competitor->id,
                'captured_at' => $now,
                'price_cents' => $normalized['price_cents'],
                'currency' => $normalized['currency'],
                'price_base_cents' => $normalized['price_base_cents'],
                'shipping_cents' => $snapshot->shippingCents,
                'available' => $normalized['available'],
                'raw_price_text' => $snapshot->rawPriceText,
                'fetch_log_id' => $fetchLog->id,
            ]);
        }

        ContentSnapshot::query()->create([
            'tenant_id' => $competitor->tenant_id,
            'competitor_product_id' => $competitor->id,
            'captured_at' => $now,
            'title' => $this->redact($snapshot->title),
            'description_md' => $this->redact($snapshot->description),
            'attributes' => $snapshot->attributes,
            'og' => $snapshot->og,
            'jsonld' => $snapshot->jsonld,
            'images' => $snapshot->images,
            'html_hash' => $snapshot->htmlHash,
        ]);

        StockObservation::query()->create([
            'tenant_id' => $competitor->tenant_id,
            'competitor_product_id' => $competitor->id,
            'captured_at' => $now,
            'available' => $snapshot->available,
            'qty_estimate' => $snapshot->stockQty,
            'buybox_winner' => $snapshot->buyboxSeller !== null ? true : null,
            'seller_name' => $snapshot->buyboxSeller,
            'seller_rating' => $snapshot->sellerRating,
        ]);

        if ($snapshot->promoType !== \Padosoft\PriceIntelligence\Enums\PromoType::None) {
            PromoObservation::query()->create([
                'tenant_id' => $competitor->tenant_id,
                'competitor_product_id' => $competitor->id,
                'captured_at' => $now,
                'promo_type' => $snapshot->promoType->value,
            ]);
        }

        $competitor->forceFill(['last_seen_at' => $now])->save();

        $this->alerts->fromPriceChange(
            competitor: $competitor,
            previousCents: $previous?->price_base_cents,
            currentCents: $normalized['price_base_cents'],
            ourCents: $this->ourPriceBaseCents($competitor),
            available: $snapshot->available,
        );

        return $snapshot;
    }

    private function ourPriceBaseCents(CompetitorProduct $competitor): ?int
    {
        $product = $competitor->target?->product;

        if ($product === null || $product->our_price_cents === null) {
            return null;
        }

        $currency = $product->currency ?? (string) config('price-intelligence.fx.base', 'EUR');
        $base = (string) config('price-intelligence.fx.base', 'EUR');

        return app(\Padosoft\PriceIntelligence\Contracts\FxProviderInterface::class)
            ->convert($product->our_price_cents, $currency, $base);
    }
}
