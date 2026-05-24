<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\ReviewInsight;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ObservationApiTest extends TestCase
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
    public function price_observations_are_listed_with_filters(): void
    {
        $key = $this->auth();
        PriceObservation::query()->create([
            'competitor_product_id' => 7,
            'captured_at' => now(),
            'price_cents' => 18900,
            'currency' => 'EUR',
            'price_base_cents' => 18900,
            'available' => true,
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/observations/prices?competitor_product_id=7')
            ->assertOk()
            ->assertJsonPath('data.0.price_cents', 18900);
    }

    #[Test]
    public function competitor_product_detail_includes_latest_snapshots(): void
    {
        $key = $this->auth();
        $cp = CompetitorProduct::query()->create([
            'monitoring_target_id' => 1,
            'url' => 'https://amazon.it/dp/X',
            'match_status' => MatchStatus::Confirmed,
        ]);
        PriceObservation::query()->create([
            'competitor_product_id' => $cp->id,
            'captured_at' => now(),
            'price_cents' => 17500,
            'currency' => 'EUR',
            'price_base_cents' => 17500,
            'available' => true,
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson("/api/v1/competitor-products/{$cp->id}")
            ->assertOk()
            ->assertJsonPath('data.competitor_product.url', 'https://amazon.it/dp/X')
            ->assertJsonPath('data.latest_price.price_cents', 17500);
    }

    #[Test]
    public function reviews_are_listed(): void
    {
        $key = $this->auth();
        ReviewInsight::query()->create([
            'competitor_product_id' => 3,
            'period' => '2026-W21',
            'sentiment_score' => 0.62,
            'themes' => [['theme' => 'battery', 'polarity' => 'pos']],
            'sample_count' => 120,
            'is_ai_generated' => true,
            'generated_at' => now(),
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/reviews?period=2026-W21')
            ->assertOk()
            ->assertJsonPath('data.0.sample_count', 120);
    }

    #[Test]
    public function observation_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/observations/prices')->assertUnauthorized();
        $this->getJson('/api/v1/competitor-products/1')->assertUnauthorized();
        $this->getJson('/api/v1/reviews')->assertUnauthorized();
    }
}
