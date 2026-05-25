<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Marketplaces;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\CompetitorSource;
use Padosoft\PriceIntelligence\Models\FetchLog;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Scraping\ScrapeService;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MarketplaceApiAdapterTest extends TestCase
{
    use RefreshDatabase;

    private function competitor(string $host, AdapterCode $code, string $url): CompetitorProduct
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $source = CompetitorSource::create([
            'host' => $host,
            'adapter_code' => $code->value,
            'robots_policy' => 'respect',
        ]);
        $product = Product::create(['external_id' => 'SKU-1', 'name' => 'Echo Dot']);
        $target = MonitoringTarget::create([
            'product_id' => $product->id,
            'country' => 'IT',
            'frequency_preset' => 'daily',
            'status' => 'active',
        ]);

        return CompetitorProduct::create([
            'tenant_id' => $tenant->id,
            'monitoring_target_id' => $target->id,
            'competitor_source_id' => $source->id,
            'url' => $url,
            'match_status' => 'confirmed',
        ]);
    }

    #[Test]
    public function amazon_keepa_driver_prices_from_the_api(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.driver', 'keepa');
        config()->set('price-intelligence.marketplaces.amazon.keepa.key', 'k');

        Http::fake(['api.keepa.com/*' => Http::response([
            'products' => [['asin' => 'B07PFFMP9P', 'title' => 'Echo Dot', 'stats' => ['current' => [5499]]]],
        ], 200)]);

        $competitor = $this->competitor('amazon.it', AdapterCode::Amazon, 'https://www.amazon.it/dp/B07PFFMP9P');
        $snapshot = app(ScrapeService::class)->scrapeAndStore($competitor);

        $this->assertSame(5499, $snapshot->priceCents);
        $this->assertSame('amazon', FetchLog::query()->sole()->driver);
    }

    #[Test]
    public function amazon_auto_without_credentials_falls_back_to_scrape(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.driver', 'auto');
        config()->set('price-intelligence.marketplaces.amazon.keepa.key', null);
        config()->set('price-intelligence.marketplaces.amazon.sp_api', []);

        Http::fake(['amazon.it/*' => Http::response(
            '<script type="application/ld+json">{"@type":"Product","name":"Echo Dot","offers":{"price":"42,99","priceCurrency":"EUR"}}</script>',
            200,
        )]);

        $competitor = $this->competitor('amazon.it', AdapterCode::Amazon, 'https://www.amazon.it/dp/B07PFFMP9P');
        $snapshot = app(ScrapeService::class)->scrapeAndStore($competitor);

        $this->assertTrue($snapshot->reachable);
        $this->assertSame(4299, $snapshot->priceCents);
    }

    #[Test]
    public function farfetch_host_uses_farfetch_adapter_and_scrapes_by_default(): void
    {
        config()->set('price-intelligence.marketplaces.farfetch.driver', 'scrape');

        Http::fake(['farfetch.com/*' => Http::response(
            '<script type="application/ld+json">{"@type":"Product","name":"Logo Tee","brand":"Gucci","offers":{"price":"450,00","priceCurrency":"EUR"}}</script>',
            200,
        )]);

        $competitor = $this->competitor('farfetch.com', AdapterCode::Farfetch, 'https://www.farfetch.com/it/shopping/gucci-item-21380995.aspx');
        $snapshot = app(ScrapeService::class)->scrapeAndStore($competitor);

        $this->assertTrue($snapshot->reachable);
        $this->assertSame(45000, $snapshot->priceCents);
        $this->assertSame('farfetch', FetchLog::query()->sole()->driver);
    }
}
