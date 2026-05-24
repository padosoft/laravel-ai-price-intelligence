<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Enums\AlertType;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Enums\Severity;
use Padosoft\PriceIntelligence\Models\Alert;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class StatsAndStreamTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): string
    {
        $tenant = Tenant::create(['code' => 't', 'name' => 'T']);
        [, $key] = ApiKey::issue($tenant->id, 'k', ['*']);
        app(TenantContext::class)->set($tenant->id);

        return $key;
    }

    #[Test]
    public function dashboard_stats_aggregate_tenant_data(): void
    {
        $key = $this->auth();
        MonitoringTarget::query()->create(['product_id' => 1, 'country' => 'IT', 'status' => 'active', 'priority' => 100]);
        CompetitorProduct::query()->create(['monitoring_target_id' => 1, 'url' => 'https://a.it/1', 'match_status' => MatchStatus::Confirmed]);
        CompetitorProduct::query()->create(['monitoring_target_id' => 1, 'url' => 'https://a.it/2', 'match_status' => MatchStatus::Suggested]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonPath('data.targets_active', 1)
            ->assertJsonPath('data.competitors_monitored', 1)
            ->assertJsonPath('data.matches_pending', 1);
    }

    #[Test]
    public function alerts_stream_emits_unacknowledged_alerts_as_sse(): void
    {
        $key = $this->auth();
        $alert = Alert::query()->create([
            'type' => AlertType::PriceDropped,
            'severity' => Severity::High,
            'payload' => ['delta_pct' => -12],
        ]);

        $response = $this->withHeader('X-Api-Key', $key)->get('/api/v1/alerts/stream');
        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('event: alert', $body);
        $this->assertStringContainsString('id: '.$alert->id, $body);
        $this->assertStringContainsString('event: heartbeat', $body);
    }

    #[Test]
    public function alerts_stream_respects_after_cursor(): void
    {
        $key = $this->auth();
        $alert = Alert::query()->create([
            'type' => AlertType::PriceDropped,
            'severity' => Severity::Medium,
            'payload' => [],
        ]);

        $body = $this->withHeader('X-Api-Key', $key)
            ->get('/api/v1/alerts/stream?after='.$alert->id)
            ->streamedContent();

        // Nothing newer than the cursor -> only a heartbeat, no alert event.
        $this->assertStringNotContainsString('event: alert', $body);
        $this->assertStringContainsString('event: heartbeat', $body);
    }

    #[Test]
    public function stats_and_stream_require_auth(): void
    {
        $this->getJson('/api/v1/stats')->assertUnauthorized();
        $this->get('/api/v1/alerts/stream')->assertUnauthorized();
    }
}
