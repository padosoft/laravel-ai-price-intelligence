<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Data\ApiProductResult;
use Padosoft\PriceIntelligence\Support\Pricing\PriceParser;

/**
 * Google Shopping product lookup via a SerpApi-compatible endpoint
 * (engine=google_product). Returns null when no api key is configured.
 */
final class SerpShoppingClient
{
    public function fetchByProductId(string $productId): ?ApiProductResult
    {
        $cfg = (array) config('price-intelligence.marketplaces.google_shopping.serp', []);
        $key = $cfg['key'] ?? null;
        if (! is_string($key) || $key === '') {
            return null;
        }

        $endpoint = (string) ($cfg['endpoint'] ?? 'https://serpapi.com/search');

        $response = Http::timeout(20)->get($endpoint, [
            'engine' => 'google_product',
            'product_id' => $productId,
            'gl' => (string) ($cfg['gl'] ?? 'it'),
            'hl' => (string) ($cfg['hl'] ?? 'it'),
            'api_key' => $key,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $product = $response->json('product_results');
        if (! is_array($product)) {
            return null;
        }

        $priceText = data_get($product, 'prices.0')
            ?? $response->json('sellers_results.online_sellers.0.base_price');
        $parsed = is_string($priceText) ? PriceParser::parse($priceText) : null;

        $image = data_get($product, 'media.0.link');
        $title = data_get($product, 'title');

        return new ApiProductResult(
            priceCents: $parsed['cents'] ?? null,
            currency: $parsed['currency'] ?? null,
            available: $parsed !== null,
            title: is_string($title) ? $title : null,
            images: is_string($image) ? [$image] : [],
            externalRef: $productId,
        );
    }
}
