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
    public function intelligence_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/forecasts')->assertUnauthorized();
        $this->getJson('/api/v1/anomalies')->assertUnauthorized();
    }
}
