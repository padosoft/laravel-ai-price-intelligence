<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Marketplaces;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\SerpShoppingClient;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SerpShoppingClientTest extends TestCase
{
    #[Test]
    public function it_returns_null_without_api_key(): void
    {
        config()->set('price-intelligence.marketplaces.google_shopping.serp.key', null);

        $this->assertNull(app(SerpShoppingClient::class)->fetchByProductId('123'));
    }

    #[Test]
    public function it_maps_a_serp_product_response(): void
    {
        config()->set('price-intelligence.marketplaces.google_shopping.serp', [
            'key' => 'serpkey',
            'endpoint' => 'https://serpapi.com/search',
            'gl' => 'it',
            'hl' => 'it',
        ]);

        Http::fake(['serpapi.com/*' => Http::response([
            'product_results' => [
                'title' => 'Pixel 8',
                'prices' => ['€699,00'],
                'media' => [['link' => 'https://img/pixel.jpg']],
            ],
        ], 200)]);

        $result = app(SerpShoppingClient::class)->fetchByProductId('123');

        $this->assertNotNull($result);
        $this->assertSame(69900, $result->priceCents);
        $this->assertSame('EUR', $result->currency);
        $this->assertSame('Pixel 8', $result->title);
        $this->assertSame(['https://img/pixel.jpg'], $result->images);
        $this->assertTrue($result->available);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'engine=google_product')
            && str_contains($r->url(), 'product_id=123')
            && str_contains($r->url(), 'api_key=serpkey'));
    }
}
