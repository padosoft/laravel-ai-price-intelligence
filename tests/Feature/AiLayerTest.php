<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Padosoft\PriceIntelligence\Contracts\AnomalyDetectorInterface;
use Padosoft\PriceIntelligence\Contracts\ForecastProviderInterface;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\AiDecisionLogger;
use Padosoft\PriceIntelligence\Services\Ai\StatisticalAnomalyDetector;
use Padosoft\PriceIntelligence\Services\Ai\StatisticalForecaster;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;

final class AiLayerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ai_migrations_create_tables(): void
    {
        $this->assertTrue(\Schema::hasTable('pi_forecasts'));
        $this->assertTrue(\Schema::hasTable('pi_anomalies'));
        $this->assertTrue(\Schema::hasTable('pi_ai_decision_logs'));
    }

    #[Test]
    public function container_binds_statistical_drivers_by_default(): void
    {
        $this->assertInstanceOf(StatisticalForecaster::class, app(ForecastProviderInterface::class));
        $this->assertInstanceOf(StatisticalAnomalyDetector::class, app(AnomalyDetectorInterface::class));
    }

    #[Test]
    public function decision_logger_records_when_enabled(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', true);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $log = app(AiDecisionLogger::class)->record(
            tenantId: $tenant->id,
            feature: 'forecast',
            output: ['forecast_price_cents' => 12345],
            model: 'statistical-v1',
            confidence: 80,
        );

        $this->assertNotNull($log);
        $this->assertSame(1, AiDecisionLog::query()->count());
        $this->assertSame('forecast', AiDecisionLog::query()->sole()->feature);
    }

    #[Test]
    public function decision_logger_persists_model_version(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', true);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        app(AiDecisionLogger::class)->record(
            tenantId: $tenant->id,
            feature: 'forecast',
            output: ['x' => 1],
            model: 'statistical',
            modelVersion: 'statistical-v1',
        );

        $this->assertSame('statistical-v1', AiDecisionLog::query()->sole()->model_version);
    }

    #[Test]
    public function disabled_ai_toggles_bind_null_drivers(): void
    {
        config()->set('price-intelligence.ai.forecast.enabled', false);
        config()->set('price-intelligence.ai.anomaly.enabled', false);

        $this->assertInstanceOf(
            \Padosoft\PriceIntelligence\Services\Ai\NullForecaster::class,
            app(ForecastProviderInterface::class),
        );
        $this->assertInstanceOf(
            \Padosoft\PriceIntelligence\Services\Ai\NullAnomalyDetector::class,
            app(AnomalyDetectorInterface::class),
        );
    }

    #[Test]
    public function decision_logger_is_noop_when_disabled(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', false);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $log = app(AiDecisionLogger::class)->record($tenant->id, 'forecast', ['x' => 1]);

        $this->assertNull($log);
        $this->assertSame(0, AiDecisionLog::query()->count());
    }
}
