<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Models\PriceDailyAggregate;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DailyAggregatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_materializes_min_max_avg_per_competitor_day(): void
    {
        $tenant = Tenant::create(['code' => 't', 'name' => 'T']);
        app(TenantContext::class)->set($tenant->id);

        $day = now()->subDay();
        foreach ([1000, 2000, 3000] as $price) {
            PriceObservation::query()->create([
                'tenant_id' => $tenant->id,
                'competitor_product_id' => 42,
                'captured_at' => $day->copy()->setTime(10, 0),
                'price_cents' => $price,
                'currency' => 'EUR',
                'price_base_cents' => $price,
                'available' => true,
            ]);
        }

        $this->artisan('piprice:aggregates:daily', ['--date' => $day->toDateString()])->assertSuccessful();

        $agg = PriceDailyAggregate::query()->where('competitor_product_id', 42)->sole();
        $this->assertSame(1000, $agg->min_price_cents);
        $this->assertSame(3000, $agg->max_price_cents);
        $this->assertSame(2000, $agg->avg_price_cents);
        $this->assertSame(3, $agg->samples);
    }

    #[Test]
    public function re_running_is_idempotent(): void
    {
        $tenant = Tenant::create(['code' => 't', 'name' => 'T']);
        app(TenantContext::class)->set($tenant->id);

        $day = now()->subDay();
        PriceObservation::query()->create([
            'tenant_id' => $tenant->id,
            'competitor_product_id' => 7,
            'captured_at' => $day->copy()->setTime(9, 0),
            'price_cents' => 5000,
            'currency' => 'EUR',
            'price_base_cents' => 5000,
            'available' => true,
        ]);

        $this->artisan('piprice:aggregates:daily', ['--date' => $day->toDateString()])->assertSuccessful();
        $this->artisan('piprice:aggregates:daily', ['--date' => $day->toDateString()])->assertSuccessful();

        $this->assertSame(1, PriceDailyAggregate::query()->where('competitor_product_id', 7)->count());
    }
}
