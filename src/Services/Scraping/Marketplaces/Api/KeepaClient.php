<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Data\ApiProductResult;

final class KeepaClient
{
    public function fetchByAsin(string $asin): ?ApiProductResult
    {
        $key = config('price-intelligence.marketplaces.amazon.keepa.key');
        if (! is_string($key) || $key === '') {
            return null;
        }

        $endpoint = rtrim((string) config('price-intelligence.marketplaces.amazon.keepa.endpoint', 'https://api.keepa.com'), '/');
        $domain = (int) config('price-intelligence.marketplaces.amazon.keepa.domain', 8);

        $response = Http::timeout(20)->get($endpoint.'/product', [
            'key' => $key,
            'domain' => $domain,
            'asin' => $asin,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $product = $response->json('products.0');
        if (! is_array($product)) {
            return null;
        }

        $current = data_get($product, 'stats.current.0', -1);
        $priceCents = is_numeric($current) && (int) $current >= 0 ? (int) $current : null;

        $ean = data_get($product, 'eanList.0');
        $title = data_get($product, 'title');
        $brand = data_get($product, 'brand');
        $resultAsin = data_get($product, 'asin');

        return new ApiProductResult(
            priceCents: $priceCents,
            currency: $this->currencyForDomain($domain),
            available: $priceCents !== null,
            title: is_string($title) ? $title : null,
            brand: is_string($brand) ? $brand : null,
            gtin: is_string($ean) ? $ean : null,
            externalRef: is_string($resultAsin) ? $resultAsin : $asin,
        );
    }

    private function currencyForDomain(int $domain): string
    {
        return match ($domain) {
            1 => 'USD',
            2 => 'GBP',
            6 => 'CAD',
            10 => 'JPY',
            default => 'EUR',
        };
    }
}
