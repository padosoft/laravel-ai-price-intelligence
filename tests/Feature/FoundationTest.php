<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;

final class FoundationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migrations_create_core_tables(): void
    {
        $this->assertTrue(\Schema::hasTable('pi_tenants'));
        $this->assertTrue(\Schema::hasTable('pi_products'));
        $this->assertTrue(\Schema::hasTable('pi_monitoring_targets'));
        $this->assertTrue(\Schema::hasTable('pi_competitor_sources'));
        $this->assertTrue(\Schema::hasTable('pi_competitor_products'));
    }

    #[Test]
    public function health_endpoint_responds(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    #[Test]
    public function tenant_scope_filters_products_and_autofills_tenant_id(): void
    {
        $t1 = Tenant::create(['code' => 't1', 'name' => 'Tenant 1']);
        $t2 = Tenant::create(['code' => 't2', 'name' => 'Tenant 2']);

        $context = app(TenantContext::class);

        $context->set($t1->id);
        Product::create(['external_id' => 'A', 'name' => 'Product A']);

        $context->set($t2->id);
        Product::create(['external_id' => 'B', 'name' => 'Product B']);

        // Under tenant 2 we only see B.
        $this->assertSame(['B'], Product::query()->pluck('external_id')->all());

        // Under tenant 1 we only see A, with tenant_id auto-filled.
        $context->set($t1->id);
        $a = Product::query()->sole();
        $this->assertSame('A', $a->external_id);
        $this->assertSame($t1->id, (int) $a->tenant_id);
    }

    #[Test]
    public function facade_exposes_version(): void
    {
        $this->assertIsString(\Padosoft\PriceIntelligence\Facades\PriceIntelligence::version());
    }
}
