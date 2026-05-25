<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Data\ApiProductResult;

/**
 * Amazon SP-API pricing client.
 *
 * NOTE on auth: Amazon **deprecated the AWS Signature V4 (SigV4) requirement** for SP-API in 2023
 * ("Removing IAM/AWS SigV4 from the SP-API authentication model"). Modern requests authenticate with
 * only the LWA access token in the `x-amz-access-token` header — no AWS credentials/region/service
 * signing. This client therefore exchanges the refresh token for an LWA access token (cached just
 * under its TTL) and calls Product Pricing with that bearer header. Returns null when credentials
 * are absent. If an integrator targets a legacy/grandfathered endpoint that still mandates SigV4,
 * they can register a custom adapter via config('price-intelligence.adapters').
 */
final class AmazonSpApiClient
{
    public function fetchOffers(string $asin): ?ApiProductResult
    {
        $cfg = (array) config('price-intelligence.marketplaces.amazon.sp_api', []);
        foreach (['client_id', 'client_secret', 'refresh_token'] as $required) {
            if (empty($cfg[$required])) {
                return null;
            }
        }

        $token = $this->accessToken($cfg);
        if ($token === null) {
            return null;
        }

        $endpoint = rtrim((string) ($cfg['endpoint'] ?? 'https://sellingpartnerapi-eu.amazon.com'), '/');
        $marketplaceId = (string) ($cfg['marketplace_id'] ?? '');

        $response = Http::withHeaders(['x-amz-access-token' => $token])
            ->timeout(20)
            ->get($endpoint.'/products/pricing/v0/items/'.$asin.'/offers', [
                'MarketplaceId' => $marketplaceId,
                'ItemCondition' => 'New',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $summary = $response->json('payload.Summary');
        if (! is_array($summary)) {
            return null;
        }

        $priceMajor = $summary['BuyBoxPrices'][0]['LandedPrice']['Amount']
            ?? $summary['LowestPrices'][0]['LandedPrice']['Amount']
            ?? null;
        $currency = $summary['BuyBoxPrices'][0]['LandedPrice']['CurrencyCode']
            ?? $summary['LowestPrices'][0]['LandedPrice']['CurrencyCode']
            ?? null;
        $offerCount = (int) ($summary['TotalOfferCount'] ?? 0);

        $priceCents = is_numeric($priceMajor) ? (int) round((float) $priceMajor * 100) : null;

        return new ApiProductResult(
            priceCents: $priceCents,
            currency: is_string($currency) ? $currency : null,
            available: $priceCents !== null && $offerCount > 0,
            externalRef: $asin,
        );
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function accessToken(array $cfg): ?string
    {
        // Cache slightly under the 3600 s LWA TTL to avoid clock-skew races.
        $cacheKey = 'pi_lwa_token_'.md5((string) $cfg['client_id'].(string) $cfg['refresh_token']);

        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $response = Http::asForm()->timeout(20)->post(
            (string) ($cfg['token_endpoint'] ?? 'https://api.amazon.com/auth/o2/token'),
            [
                'grant_type' => 'refresh_token',
                'refresh_token' => (string) $cfg['refresh_token'],
                'client_id' => (string) $cfg['client_id'],
                'client_secret' => (string) $cfg['client_secret'],
            ],
        );

        $token = $response->successful() ? $response->json('access_token') : null;

        if (is_string($token)) {
            Cache::put($cacheKey, $token, 3500);
        }

        return is_string($token) ? $token : null;
    }
}
