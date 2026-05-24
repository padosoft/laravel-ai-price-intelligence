<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Enums\RuleStrategy;
use Padosoft\PriceIntelligence\Jobs\ScrapeCompetitorProductJob;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\RepricingRule;
use Padosoft\PriceIntelligence\Models\RuleDecision;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ActionsApiTest extends TestCase
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
    public function rule_simulate_previews_without_persisting(): void
    {
        $key = $this->auth();
        $rule = RepricingRule::query()->create([
            'name' => 'Undercut 5%',
            'strategy' => RuleStrategy::UndercutPct,
            'parameters' => ['delta_pct' => -5],
            'priority' => 10,
            'status' => 'active',
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->postJson("/api/v1/rules/{$rule->id}/simulate", [
                'samples' => [
                    ['product_id' => 1, 'current_price_cents' => 10000, 'competitor_prices_cents' => [9000, 9500]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.strategy', 'undercut_pct')
            ->assertJsonPath('data.decisions.0.product_id', 1)
            ->assertJsonStructure(['data' => ['decisions' => [['current_price_cents', 'suggested_price_cents', 'changed']]]]);

        // Dry-run must not persist any decision.
        $this->assertSame(0, RuleDecision::query()->count());
    }

    #[Test]
    public function scrape_now_queues_a_job_per_confirmed_competitor(): void
    {
        Queue::fake();
        $key = $this->auth();

        $target = MonitoringTarget::query()->create([
            'product_id' => 1,
            'country' => 'IT',
            'status' => 'active',
            'priority' => 100,
        ]);
        foreach (['confirmed', 'confirmed', 'suggested'] as $status) {
            CompetitorProduct::query()->create([
                'monitoring_target_id' => $target->id,
                'url' => 'https://amazon.it/'.$status.'-'.uniqid(),
                'match_status' => MatchStatus::from($status),
            ]);
        }

        $this->withHeader('X-Api-Key', $key)
            ->postJson("/api/v1/targets/{$target->id}/scrape:now")
            ->assertStatus(202)
            ->assertJsonPath('data.queued', 2); // only the 2 confirmed

        Queue::assertPushed(ScrapeCompetitorProductJob::class, 2);
    }

    #[Test]
    public function action_endpoints_require_auth(): void
    {
        $this->postJson('/api/v1/targets/1/scrape:now')->assertUnauthorized();
        $this->postJson('/api/v1/rules/1/simulate', ['samples' => []])->assertUnauthorized();
    }
}
