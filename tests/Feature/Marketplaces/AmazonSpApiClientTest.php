<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Marketplaces;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\AmazonSpApiClient;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AmazonSpApiClientTest extends TestCase
{
    private function configureCreds(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.sp_api', [
            'client_id' => 'cid',
            'client_secret' => 'secret',
            'refresh_token' => 'rt',
            'endpoint' => 'https://sellingpartnerapi-eu.amazon.com',
            'token_endpoint' => 'https://api.amazon.com/auth/o2/token',
            'marketplace_id' => 'APJ6JRA9NG5V4',
        ]);
    }

    #[Test]
    public function it_returns_null_without_credentials(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.sp_api', []);

        $this->assertNull(app(AmazonSpApiClient::class)->fetchOffers('B07PFFMP9P'));
    }

    #[Test]
    public function it_exchanges_token_then_maps_offers(): void
    {
        $this->configureCreds();

        Http::fake([
            'api.amazon.com/auth/o2/token' => Http::response(['access_token' => 'atk', 'expires_in' => 3600], 200),
            'sellingpartnerapi-eu.amazon.com/*' => Http::response([
                'payload' => [
                    'Summary' => [
                        'LowestPrices' => [['LandedPrice' => ['Amount' => 54.99, 'CurrencyCode' => 'EUR']]],
                        'BuyBoxPrices' => [['LandedPrice' => ['Amount' => 54.99, 'CurrencyCode' => 'EUR']]],
                        'TotalOfferCount' => 3,
                    ],
                ],
            ], 200),
        ]);

        $result = app(AmazonSpApiClient::class)->fetchOffers('B07PFFMP9P');

        $this->assertNotNull($result);
        $this->assertSame(5499, $result->priceCents);
        $this->assertSame('EUR', $result->currency);
        $this->assertTrue($result->available);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/products/pricing/v0/items/B07PFFMP9P/offers')
            && $r->hasHeader('x-amz-access-token', 'atk'));
    }

    #[Test]
    public function zero_offers_means_unavailable(): void
    {
        $this->configureCreds();

        Http::fake([
            'api.amazon.com/auth/o2/token' => Http::response(['access_token' => 'atk'], 200),
            'sellingpartnerapi-eu.amazon.com/*' => Http::response(['payload' => ['Summary' => ['TotalOfferCount' => 0]]], 200),
        ]);

        $result = app(AmazonSpApiClient::class)->fetchOffers('X');

        $this->assertNotNull($result);
        $this->assertNull($result->priceCents);
        $this->assertFalse($result->available);
    }
}
