<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Data\ApiProductResult;

/**
 * eBay Browse API client: client-credentials OAuth token, then getItemByLegacyId.
 * Returns null when credentials are absent.
 */
final class EbayBrowseClient
{
    public function fetchByLegacyId(string $legacyId): ?ApiProductResult
    {
        $cfg = (array) config('price-intelligence.marketplaces.ebay', []);
        if (empty($cfg['client_id']) || empty($cfg['client_secret'])) {
            return null;
        }

        $endpoint = rtrim((string) ($cfg['endpoint'] ?? 'https://api.ebay.com'), '/');
        $marketplaceId = (string) ($cfg['marketplace_id'] ?? 'EBAY_US');

        $token = $this->token($endpoint, (string) $cfg['client_id'], (string) $cfg['client_secret']);
        if ($token === null) {
            return null;
        }

        $response = Http::withToken($token)
            ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => $marketplaceId])
            ->timeout(20)
            ->get($endpoint.'/buy/browse/v1/item/getItemByLegacyId', ['legacy_item_id' => $legacyId]);

        if (! $response->successful()) {
            return null;
        }

        $value = $response->json('price.value');
        $priceCents = is_numeric($value) ? (int) round((float) $value * 100) : null;
        $status = $response->json('estimatedAvailabilities.0.estimatedAvailabilityStatus');
        $image = $response->json('image.imageUrl');
        $currency = $response->json('price.currency');
        $title = $response->json('title');
        $brand = $response->json('brand');

        return new ApiProductResult(
            priceCents: $priceCents,
            currency: is_string($currency) ? $currency : null,
            available: $priceCents !== null && $status !== 'OUT_OF_STOCK',
            title: is_string($title) ? $title : null,
            brand: is_string($brand) ? $brand : null,
            images: is_string($image) ? [$image] : [],
            externalRef: $legacyId,
        );
    }

    private function token(string $endpoint, string $clientId, string $clientSecret): ?string
    {
        // Cache slightly under the 7200 s eBay CC token TTL.
        $cacheKey = 'pi_ebay_token_'.md5($clientId.$endpoint);

        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->timeout(20)
            ->post($endpoint.'/identity/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'https://api.ebay.com/oauth/api_scope',
            ]);

        $token = $response->successful() ? $response->json('access_token') : null;

        if (is_string($token)) {
            Cache::put($cacheKey, $token, 7000);
        }

        return is_string($token) ? $token : null;
    }
}
