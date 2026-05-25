<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Marketplaces;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\FarfetchClient;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FarfetchClientTest extends TestCase
{
    #[Test]
    public function retailed_returns_null_without_key(): void
    {
        config()->set('price-intelligence.marketplaces.farfetch.retailed.key', null);

        $this->assertNull(app(FarfetchClient::class)->fetchViaRetailed('https://www.farfetch.com/x-item-1.aspx'));
    }

    #[Test]
    public function apify_returns_null_without_token(): void
    {
        config()->set('price-intelligence.marketplaces.farfetch.apify.token', null);

        $this->assertNull(app(FarfetchClient::class)->fetchViaApify('https://www.farfetch.com/x-item-1.aspx'));
    }

    #[Test]
    public function retailed_maps_a_product_and_sends_api_key(): void
    {
        config()->set('price-intelligence.marketplaces.farfetch.retailed', [
            'key' => 'rk',
            'endpoint' => 'https://app.retailed.io/api/v1/scraper/farfetch/product',
        ]);

        Http::fake(['app.retailed.io/*' => Http::response([
            'title' => 'Logo T-Shirt',
            'brand' => 'Gucci',
            'price' => ['amount' => '450.00', 'currency' => 'EUR'],
            'images' => ['https://cdn.farfetch/x.jpg'],
            'availability' => 'in_stock',
        ], 200)]);

        $result = app(FarfetchClient::class)->fetchViaRetailed('https://www.farfetch.com/it/shopping/gucci-item-21380995.aspx');

        $this->assertNotNull($result);
        $this->assertSame(45000, $result->priceCents);
        $this->assertSame('EUR', $result->currency);
        $this->assertSame('Logo T-Shirt', $result->title);
        $this->assertSame('Gucci', $result->brand);
        $this->assertSame(['https://cdn.farfetch/x.jpg'], $result->images);
        $this->assertTrue($result->available);

        Http::assertSent(fn ($r) => $r->hasHeader('x-api-key', 'rk') && str_contains($r->url(), 'url='));
    }

    #[Test]
    public function apify_maps_first_dataset_item(): void
    {
        config()->set('price-intelligence.marketplaces.farfetch.apify', [
            'token' => 'tok',
            'actor' => 'autofacts~farfetch',
            'endpoint' => 'https://api.apify.com/v2',
        ]);

        Http::fake(['api.apify.com/*' => Http::response([
            ['title' => 'Sneakers', 'brand' => 'Prada', 'priceValue' => 690, 'currency' => 'EUR', 'availability' => 'in_stock'],
        ], 200)]);

        $result = app(FarfetchClient::class)->fetchViaApify('https://www.farfetch.com/it/shopping/prada-item-1.aspx');

        $this->assertNotNull($result);
        $this->assertSame(69000, $result->priceCents);
        $this->assertSame('Prada', $result->brand);
        $this->assertTrue($result->available);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'run-sync-get-dataset-items') && str_contains($r->url(), 'token=tok'));
    }
}
