<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Padosoft\PriceIntelligence\Enums\Frequency;
use Padosoft\PriceIntelligence\Jobs\ScrapeCompetitorProductJob;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Scheduling\TargetScheduler;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;

final class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_jobs_for_due_targets_and_reschedules(): void
    {
        Bus::fake();

        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $product = Product::create(['external_id' => 'SKU-1', 'name' => 'Phone']);
        $target = MonitoringTarget::create([
            'product_id' => $product->id, 'country' => 'IT',
            'frequency_preset' => Frequency::Daily, 'status' => 'active',
            'next_check_at' => now()->subHour(),
        ]);
        CompetitorProduct::create([
            'tenant_id' => $tenant->id, 'monitoring_target_id' => $target->id,
            'url' => 'https://a.it/1', 'match_status' => 'confirmed',
        ]);
        CompetitorProduct::create([
            'tenant_id' => $tenant->id, 'monitoring_target_id' => $target->id,
            'url' => 'https://a.it/2', 'match_status' => 'suggested', // not dispatched
        ]);

        // Run scheduler without tenant scope (cross-tenant worker).
        app(TenantContext::class)->forget();
        $dispatched = app(TargetScheduler::class)->dispatchDue();

        $this->assertSame(1, $dispatched);
        Bus::assertDispatchedTimes(ScrapeCompetitorProductJob::class, 1);

        // next_check_at advanced into the future.
        $this->assertTrue($target->fresh()->next_check_at->isFuture());
    }

    #[Test]
    public function it_skips_targets_not_yet_due(): void
    {
        Bus::fake();

        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);
        $product = Product::create(['external_id' => 'SKU-1', 'name' => 'Phone']);
        $target = MonitoringTarget::create([
            'product_id' => $product->id, 'country' => 'IT',
            'frequency_preset' => Frequency::Daily, 'status' => 'active',
            'next_check_at' => now()->addDay(),
        ]);
        CompetitorProduct::create([
            'tenant_id' => $tenant->id, 'monitoring_target_id' => $target->id,
            'url' => 'https://a.it/1', 'match_status' => 'confirmed',
        ]);

        app(TenantContext::class)->forget();
        $this->assertSame(0, app(TargetScheduler::class)->dispatchDue());
        Bus::assertNothingDispatched();
    }
}
