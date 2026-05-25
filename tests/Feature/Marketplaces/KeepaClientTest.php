<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Marketplaces;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\KeepaClient;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class KeepaClientTest extends TestCase
{
    #[Test]
    public function it_returns_null_when_no_key_configured(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.keepa.key', null);

        $this->assertNull(app(KeepaClient::class)->fetchByAsin('B07PFFMP9P'));
    }

    #[Test]
    public function it_maps_a_keepa_product_response(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.keepa.key', 'k');
        config()->set('price-intelligence.marketplaces.amazon.keepa.domain', 8);

        Http::fake(['api.keepa.com/*' => Http::response([
            'products' => [[
                'asin' => 'B07PFFMP9P',
                'title' => 'Echo Dot',
                'brand' => 'Amazon',
                'eanList' => ['0840080553856'],
                'stats' => ['current' => [5499, -1, 5999]],
            ]],
        ], 200)]);

        $result = app(KeepaClient::class)->fetchByAsin('B07PFFMP9P');

        $this->assertNotNull($result);
        $this->assertSame(5499, $result->priceCents);
        $this->assertSame('EUR', $result->currency);
        $this->assertSame('Echo Dot', $result->title);
        $this->assertSame('Amazon', $result->brand);
        $this->assertSame('0840080553856', $result->gtin);
        $this->assertTrue($result->available);
    }

    #[Test]
    public function unavailable_price_marks_not_available(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.keepa.key', 'k');

        Http::fake(['api.keepa.com/*' => Http::response([
            'products' => [['asin' => 'X', 'title' => 'T', 'stats' => ['current' => [-1]]]],
        ], 200)]);

        $result = app(KeepaClient::class)->fetchByAsin('X');

        $this->assertNotNull($result);
        $this->assertNull($result->priceCents);
        $this->assertFalse($result->available);
    }
}
