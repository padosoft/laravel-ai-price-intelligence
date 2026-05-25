<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\CompetitorSource;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\PromoObservation;
use Padosoft\PriceIntelligence\Models\StockObservation;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ObservationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): string
    {
        $tenant = Tenant::create(['code' => 't', 'name' => 'T']);
        [, $key] = ApiKey::issue($tenant->id, 'k', ['*']);
        app(TenantContext::class)->set($tenant->id);

        return $key;
    }

    private function competitorOnHost(string $host): CompetitorProduct
    {
        $source = CompetitorSource::query()->create(['host' => $host, 'adapter_code' => 'generic', 'robots_policy' => 'respect']);
        $product = Product::query()->create(['external_id' => 'EX-'.$host, 'name' => 'P']);
        $target = MonitoringTarget::query()->create(['product_id' => $product->id, 'country' => 'IT', 'status' => 'active', 'priority' => 1]);

        return CompetitorProduct::query()->create([
            'monitoring_target_id' => $target->id,
            'competitor_source_id' => $source->id,
            'url' => 'https://'.$host.'/p',
            'match_status' => MatchStatus::Confirmed,
        ]);
    }

    #[Test]
    public function prices_can_be_filtered_by_host(): void
    {
        $key = $this->auth();
        $a = $this->competitorOnHost('amazon.it');
        $b = $this->competitorOnHost('ebay.it');
        PriceObservation::query()->create(['competitor_product_id' => $a->id, 'captured_at' => now(), 'price_cents' => 1000, 'currency' => 'EUR', 'price_base_cents' => 1000, 'available' => true]);
        PriceObservation::query()->create(['competitor_product_id' => $b->id, 'captured_at' => now(), 'price_cents' => 2000, 'currency' => 'EUR', 'price_base_cents' => 2000, 'available' => true]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/observations/prices?host=ebay.it')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price_cents', 2000);
    }

    #[Test]
    public function stock_history_is_listed_and_filterable(): void
    {
        $key = $this->auth();
        $cp = $this->competitorOnHost('amazon.it');
        StockObservation::query()->create(['competitor_product_id' => $cp->id, 'captured_at' => now(), 'available' => true, 'qty_estimate' => 5]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/observations/stock?competitor_product_id='.$cp->id)
            ->assertOk()
            ->assertJsonPath('data.0.qty_estimate', 5);
    }

    #[Test]
    public function promo_history_is_listed(): void
    {
        $key = $this->auth();
        $cp = $this->competitorOnHost('amazon.it');
        PromoObservation::query()->create(['competitor_product_id' => $cp->id, 'captured_at' => now(), 'promo_type' => 'sale', 'effective_discount_pct' => 15.0]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/observations/promos?competitor_product_id='.$cp->id)
            ->assertOk()
            ->assertJsonPath('data.0.effective_discount_pct', 15);
    }

    #[Test]
    public function history_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/observations/stock')->assertUnauthorized();
        $this->getJson('/api/v1/observations/promos')->assertUnauthorized();
    }
}
