<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\CompetitorSource;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\AmazonAdapter;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\GenericAdapter;
use Padosoft\PriceIntelligence\Services\Scraping\MarketplaceAdapterFactory;
use Padosoft\PriceIntelligence\Services\Scraping\ScrapeService;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;

final class MarketplaceAdapterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function factory_resolves_each_adapter_code(): void
    {
        $factory = app(MarketplaceAdapterFactory::class);

        $this->assertInstanceOf(AmazonAdapter::class, $factory->make(AdapterCode::Amazon));
        $this->assertInstanceOf(GenericAdapter::class, $factory->make(AdapterCode::Generic));
        $this->assertSame(AdapterCode::Ebay, $factory->make(AdapterCode::Ebay)->code());
        $this->assertSame(AdapterCode::Idealo, $factory->make(AdapterCode::Idealo)->code());
        $this->assertSame(AdapterCode::Trovaprezzi, $factory->make(AdapterCode::Trovaprezzi)->code());
        $this->assertSame(AdapterCode::GoogleShopping, $factory->make(AdapterCode::GoogleShopping)->code());
    }

    #[Test]
    public function amazon_adapter_extracts_asin_and_uses_amazon_driver_in_log(): void
    {
        Http::fake(['amazon.it/*' => Http::response(
            '<script type="application/ld+json">{"@type":"Product","name":"Echo Dot","offers":{"price":"54,99","priceCurrency":"EUR"}}</script>',
            200,
        )]);

        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $source = CompetitorSource::create([
            'host' => 'amazon.it', 'adapter_code' => AdapterCode::Amazon->value, 'robots_policy' => 'respect',
        ]);
        $product = Product::create(['external_id' => 'SKU-1', 'name' => 'Echo Dot']);
        $target = MonitoringTarget::create([
            'product_id' => $product->id, 'country' => 'IT',
            'frequency_preset' => 'daily', 'status' => 'active',
        ]);
        $competitor = CompetitorProduct::create([
            'tenant_id' => $tenant->id,
            'monitoring_target_id' => $target->id,
            'competitor_source_id' => $source->id,
            'url' => 'https://www.amazon.it/dp/B07PFFMP9P',
            'match_status' => 'confirmed',
        ]);

        app(ScrapeService::class)->scrapeAndStore($competitor);

        $this->assertSame('B07PFFMP9P', $competitor->fresh()->external_ref);
        $this->assertSame('amazon', \Padosoft\PriceIntelligence\Models\FetchLog::query()->sole()->driver);
    }
}
