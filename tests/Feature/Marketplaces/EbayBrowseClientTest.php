<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Marketplaces;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\EbayBrowseClient;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class EbayBrowseClientTest extends TestCase
{
    private function creds(): void
    {
        config()->set('price-intelligence.marketplaces.ebay', [
            'client_id' => 'cid',
            'client_secret' => 'sec',
            'endpoint' => 'https://api.ebay.com',
            'marketplace_id' => 'EBAY_IT',
        ]);
    }

    #[Test]
    public function it_returns_null_without_credentials(): void
    {
        config()->set('price-intelligence.marketplaces.ebay', ['endpoint' => 'https://api.ebay.com']);

        $this->assertNull(app(EbayBrowseClient::class)->fetchByLegacyId('123456789012'));
    }

    #[Test]
    public function it_tokenizes_then_maps_item(): void
    {
        $this->creds();

        Http::fake([
            'api.ebay.com/identity/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 7200], 200),
            'api.ebay.com/buy/browse/*' => Http::response([
                'itemId' => 'v1|123456789012|0',
                'title' => 'Vintage Camera',
                'brand' => 'Nikon',
                'price' => ['value' => '129.90', 'currency' => 'EUR'],
                'image' => ['imageUrl' => 'https://i.ebayimg.com/x.jpg'],
                'estimatedAvailabilities' => [['estimatedAvailabilityStatus' => 'IN_STOCK']],
            ], 200),
        ]);

        $result = app(EbayBrowseClient::class)->fetchByLegacyId('123456789012');

        $this->assertNotNull($result);
        $this->assertSame(12990, $result->priceCents);
        $this->assertSame('EUR', $result->currency);
        $this->assertSame('Vintage Camera', $result->title);
        $this->assertSame('Nikon', $result->brand);
        $this->assertSame(['https://i.ebayimg.com/x.jpg'], $result->images);
        $this->assertTrue($result->available);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'getItemByLegacyId')
            && $r->hasHeader('Authorization', 'Bearer tok')
            && $r->hasHeader('X-EBAY-C-MARKETPLACE-ID', 'EBAY_IT'));
    }

    #[Test]
    public function out_of_stock_is_unavailable(): void
    {
        $this->creds();

        Http::fake([
            'api.ebay.com/identity/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
            'api.ebay.com/buy/browse/*' => Http::response([
                'title' => 'Sold Out',
                'price' => ['value' => '10.00', 'currency' => 'EUR'],
                'estimatedAvailabilities' => [['estimatedAvailabilityStatus' => 'OUT_OF_STOCK']],
            ], 200),
        ]);

        $result = app(EbayBrowseClient::class)->fetchByLegacyId('1');

        $this->assertNotNull($result);
        $this->assertFalse($result->available);
    }
}
