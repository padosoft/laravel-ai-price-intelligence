<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Scraping\ScrapeService;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;

final class ScrapeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function competitor(): CompetitorProduct
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $product = Product::create(['external_id' => 'SKU-1', 'name' => 'Phone']);
        $target = MonitoringTarget::create([
            'product_id' => $product->id, 'country' => 'IT',
            'frequency_preset' => \Padosoft\PriceIntelligence\Enums\Frequency::Daily, 'status' => 'active',
        ]);

        return CompetitorProduct::create([
            'tenant_id' => $tenant->id,
            'monitoring_target_id' => $target->id,
            'url' => 'https://shop.it/p/x1',
            'match_status' => 'confirmed',
        ]);
    }

    #[Test]
    public function it_scrapes_and_persists_price_and_content(): void
    {
        Http::fake(['shop.it/*' => Http::response(
            '<script type="application/ld+json">{"@type":"Product","name":"Phone X","offers":{"price":"299,00","priceCurrency":"EUR"}}</script>',
            200,
        )]);

        $competitor = $this->competitor();

        $snapshot = app(ScrapeService::class)->scrapeAndStore($competitor, ['locale' => 'it-IT']);

        $this->assertTrue($snapshot->reachable);
        $this->assertSame(29900, $snapshot->priceCents);

        $obs = PriceObservation::query()->where('competitor_product_id', $competitor->id)->sole();
        $this->assertSame(29900, $obs->price_cents);
        $this->assertSame('EUR', $obs->currency);
        $this->assertSame(29900, $obs->price_eur_cents);

        $this->assertNotNull($competitor->fresh()->last_seen_at);
    }

    #[Test]
    public function unreachable_site_logs_but_stores_no_price(): void
    {
        Http::fake(['shop.it/*' => Http::response('', 503)]);

        $competitor = $this->competitor();

        $snapshot = app(ScrapeService::class)->scrapeAndStore($competitor);

        $this->assertFalse($snapshot->reachable);
        $this->assertSame(0, PriceObservation::query()->where('competitor_product_id', $competitor->id)->count());
        // A fetch log is still written for audit.
        $this->assertSame(1, \Padosoft\PriceIntelligence\Models\FetchLog::query()->count());
    }

    #[Test]
    public function it_converts_foreign_currency_to_base_eur(): void
    {
        Http::fake(['shop.com/*' => Http::response(
            '<script type="application/ld+json">{"@type":"Product","name":"US Phone","offers":{"price":"108.00","priceCurrency":"USD"}}</script>',
            200,
        )]);

        $tenant = Tenant::create(['code' => 't2', 'name' => 't2']);
        app(TenantContext::class)->set($tenant->id);
        $product = Product::create(['external_id' => 'SKU-2', 'name' => 'US Phone']);
        $target = MonitoringTarget::create([
            'product_id' => $product->id, 'country' => 'US',
            'frequency_preset' => \Padosoft\PriceIntelligence\Enums\Frequency::Daily, 'status' => 'active',
        ]);
        $competitor = CompetitorProduct::create([
            'tenant_id' => $tenant->id, 'monitoring_target_id' => $target->id,
            'url' => 'https://shop.com/p/us', 'match_status' => 'confirmed',
        ]);

        app(ScrapeService::class)->scrapeAndStore($competitor);

        $obs = PriceObservation::query()->where('competitor_product_id', $competitor->id)->sole();
        // 108 USD / 1.08 = 100 EUR -> 10000 cents.
        $this->assertSame(10000, $obs->price_eur_cents);
        $this->assertSame('USD', $obs->currency);
    }
}
