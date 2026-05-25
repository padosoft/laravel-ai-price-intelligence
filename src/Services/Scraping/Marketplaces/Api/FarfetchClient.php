<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Data\ApiProductResult;
use Padosoft\PriceIntelligence\Support\Pricing\PriceParser;

/**
 * Farfetch commercial-API drivers. `retailed` (app.retailed.io) and `apify`
 * (run-sync actor) are opt-in; each returns null when its key/token is absent
 * so the adapter falls back to the JSON-LD scrape path.
 */
final class FarfetchClient
{
    public function fetchViaRetailed(string $url): ?ApiProductResult
    {
        $cfg = (array) config('price-intelligence.marketplaces.farfetch.retailed', []);
        $key = $cfg['key'] ?? null;
        if (! is_string($key) || $key === '') {
            return null;
        }

        $endpoint = (string) ($cfg['endpoint'] ?? 'https://app.retailed.io/api/v1/scraper/farfetch/product');

        $response = Http::withHeaders(['x-api-key' => $key])
            ->timeout(30)
            ->get($endpoint, ['url' => $url]);

        if (! $response->successful()) {
            return null;
        }

        return $this->map($response->json(), $url);
    }

    public function fetchViaApify(string $url): ?ApiProductResult
    {
        $cfg = (array) config('price-intelligence.marketplaces.farfetch.apify', []);
        $token = $cfg['token'] ?? null;
        if (! is_string($token) || $token === '') {
            return null;
        }

        $base = rtrim((string) ($cfg['endpoint'] ?? 'https://api.apify.com/v2'), '/');
        $actor = (string) ($cfg['actor'] ?? 'autofacts~farfetch');

        $response = Http::timeout(60)->post(
            $base.'/acts/'.$actor.'/run-sync-get-dataset-items?token='.urlencode($token),
            ['startUrls' => [['url' => $url]]],
        );

        if (! $response->successful()) {
            return null;
        }

        $item = $response->json('0');

        return is_array($item) ? $this->map($item, $url) : null;
    }

    private function map(mixed $data, string $url): ?ApiProductResult
    {
        if (! is_array($data)) {
            return null;
        }

        $priceCents = null;
        $currency = null;

        // `price` may be an object ({amount, currency}) or a scalar; use data_get to avoid a
        // TypeError on the scalar shape.
        $amount = data_get($data, 'price.amount') ?? $data['priceValue'] ?? (is_array($data['price'] ?? null) ? null : ($data['price'] ?? null));
        if (is_numeric($amount)) {
            $priceCents = (int) round((float) $amount * 100);
        } elseif (is_string($amount)) {
            $parsed = PriceParser::parse($amount);
            $priceCents = $parsed['cents'] ?? null;
            $currency = $parsed['currency'] ?? null;
        }

        $currency ??= data_get($data, 'price.currency') ?? $data['currency'] ?? null;

        // Only an explicit out-of-stock verdict marks the product unavailable. When the price can't
        // be parsed (and there's no explicit OOS), leave available=true so AbstractApiAdapter falls
        // through to scraping instead of recording a false "unavailable".
        $availability = $data['availability'] ?? null;
        $available = ! in_array(strtolower((string) $availability), ['out_of_stock', 'sold_out'], true);

        $images = [];
        if (isset($data['images']) && is_array($data['images'])) {
            $images = array_values(array_filter($data['images'], 'is_string'));
        }

        return new ApiProductResult(
            priceCents: $priceCents,
            currency: is_string($currency) ? $currency : null,
            available: $available,
            title: is_string($data['title'] ?? null) ? $data['title'] : null,
            brand: is_string($data['brand'] ?? null) ? $data['brand'] : null,
            images: $images,
        );
    }
}
