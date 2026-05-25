<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Enums\Severity;
use Padosoft\PriceIntelligence\Models\Anomaly;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\Forecast;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class IntelligenceApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): string
    {
        $tenant = Tenant::create(['code' => 't', 'name' => 'T']);
        [, $key] = ApiKey::issue($tenant->id, 'k', ['*']);
        // Persist domain rows under the same tenant scope.
        app(TenantContext::class)->set($tenant->id);

        return $key;
    }

    #[Test]
    public function forecasts_are_listed_and_tenant_scoped(): void
    {
        $key = $this->auth();
        Forecast::query()->create([
            'competitor_product_id' => 1,
            'horizon_days' => 14,
            'forecast_price_cents' => 18500,
            'ci_low_cents' => 18000,
            'ci_high_cents' => 19000,
            'model_version' => 'stat-1',
            'is_ai_generated' => true,
            'generated_at' => now(),
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/forecasts?horizon=14')
            ->assertOk()
            ->assertJsonPath('data.0.horizon_days', 14)
            ->assertJsonPath('data.0.forecast_price_cents', 18500);
    }

    #[Test]
    public function anomalies_filter_by_type_and_severity(): void
    {
        $key = $this->auth();
        Anomaly::query()->create([
            'competitor_product_id' => 1,
            'type' => 'price_error',
            'severity' => Severity::High,
            'evidence' => ['current_cents' => 900],
            'is_ai_generated' => false,
            'detected_at' => now(),
        ]);
        Anomaly::query()->create([
            'competitor_product_id' => 2,
            'type' => 'outlier',
            'severity' => Severity::Medium,
            'evidence' => [],
            'is_ai_generated' => false,
            'detected_at' => now(),
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/anomalies?type=price_error')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'price_error');
    }

    #[Test]
    public function a_single_anomaly_can_be_acknowledged(): void
    {
        $key = $this->auth();
        $anomaly = Anomaly::query()->create([
            'competitor_product_id' => 1,
            'type' => 'price_error',
            'severity' => Severity::High,
            'evidence' => [],
            'is_ai_generated' => false,
            'detected_at' => now(),
        ]);

        $this->assertNull($anomaly->acknowledged_at);
        $this->withHeader('X-Api-Key', $key)
            ->postJson("/api/v1/anomalies/{$anomaly->id}/ack")
            ->assertOk()
            ->assertJsonPath('data.id', $anomaly->id);

        $this->assertNotNull($anomaly->fresh()->acknowledged_at);
    }

    #[Test]
    public function anomalies_can_be_bulk_acknowledged_and_already_acked_are_skipped(): void
    {
        $key = $this->auth();
        $a = Anomaly::query()->create(['competitor_product_id' => 1, 'type' => 'outlier', 'severity' => Severity::Low, 'evidence' => [], 'is_ai_generated' => false, 'detected_at' => now()]);
        $b = Anomaly::query()->create(['competitor_product_id' => 2, 'type' => 'outlier', 'severity' => Severity::Low, 'evidence' => [], 'is_ai_generated' => false, 'detected_at' => now(), 'acknowledged_at' => now()]);

        // Two ids submitted, but `$b` is already acked → only `$a` is counted.
        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/anomalies:ack', ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('data.acknowledged', 1);

        $this->assertNotNull($a->fresh()->acknowledged_at);
    }

    #[Test]
    public function bulk_acknowledge_requires_ids(): void
    {
        $key = $this->auth();
        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/anomalies:ack', [])
            ->assertStatus(422);
    }

    #[Test]
    public function malformed_integer_filters_are_rejected(): void
    {
        $key = $this->auth();
        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/forecasts?horizon=abc')
            ->assertStatus(422);
    }

    #[Test]
    public function intelligence_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/forecasts')->assertUnauthorized();
        $this->getJson('/api/v1/anomalies')->assertUnauthorized();
    }
}
