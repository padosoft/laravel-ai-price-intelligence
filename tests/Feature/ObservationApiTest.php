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
    public function competitor_products_are_listed_with_product_and_latest_price(): void
    {
        $key = $this->auth();
        $source = CompetitorSource::query()->create([
            'host' => 'amazon.it',
            'display_name' => 'Amazon IT',
            'country' => 'IT',
            'adapter_code' => 'generic',
            'robots_policy' => 'respect',
        ]);
        $product = Product::query()->create([
            'external_id' => 'EX1',
            'name' => 'Acme X1 Pro',
            'our_price_cents' => 79900,
            'currency' => 'EUR',
        ]);
        $target = MonitoringTarget::query()->create([
            'product_id' => $product->id,
            'country' => 'IT',
            'status' => 'active',
            'priority' => 10,
        ]);
        $cp = CompetitorProduct::query()->create([
            'monitoring_target_id' => $target->id,
            'competitor_source_id' => $source->id,
            'url' => 'https://amazon.it/dp/X',
            'match_status' => MatchStatus::Confirmed,
            'match_confidence' => 96,
        ]);
        // older + newer observation: only the latest should surface.
        PriceObservation::query()->create(['competitor_product_id' => $cp->id, 'captured_at' => now()->subDay(), 'price_cents' => 80000, 'currency' => 'EUR', 'price_base_cents' => 80000, 'available' => true]);
        PriceObservation::query()->create(['competitor_product_id' => $cp->id, 'captured_at' => now(), 'price_cents' => 75900, 'currency' => 'EUR', 'price_base_cents' => 75900, 'available' => true]);
        // a suggested listing must be excluded from the default (confirmed) view.
        CompetitorProduct::query()->create([
            'monitoring_target_id' => $target->id,
            'url' => 'https://amazon.it/dp/Y',
            'match_status' => MatchStatus::Suggested,
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/competitor-products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.url', 'https://amazon.it/dp/X')
            ->assertJsonPath('data.0.target.product.name', 'Acme X1 Pro')
            ->assertJsonPath('data.0.source.host', 'amazon.it')
            ->assertJsonPath('data.0.latest_price.price_cents', 75900);
    }

    #[Test]
    public function competitor_products_can_be_filtered_by_host(): void
    {
        $key = $this->auth();
        $amazon = CompetitorSource::query()->create(['host' => 'amazon.it', 'adapter_code' => 'generic', 'robots_policy' => 'respect']);
        $media = CompetitorSource::query()->create(['host' => 'mediaworld.it', 'adapter_code' => 'generic', 'robots_policy' => 'respect']);
        foreach ([$amazon, $media] as $src) {
            CompetitorProduct::query()->create([
                'monitoring_target_id' => 1,
                'competitor_source_id' => $src->id,
                'url' => 'https://'.$src->host.'/p',
                'match_status' => MatchStatus::Confirmed,
            ]);
        }

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/competitor-products?host=mediaworld.it')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.source.host', 'mediaworld.it');
    }

    #[Test]
    public function reviews_are_listed_when_the_module_is_enabled(): void
    {
        config()->set('price-intelligence.review_insight.enabled', true);
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
    public function reviews_return_empty_when_the_module_is_disabled(): void
    {
        config()->set('price-intelligence.review_insight.enabled', false);
        $key = $this->auth();
        ReviewInsight::query()->create([
            'competitor_product_id' => 3,
            'period' => '2026-W21',
            'sentiment_score' => 0.62,
            'sample_count' => 120,
            'is_ai_generated' => true,
            'generated_at' => now(),
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/reviews')
            ->assertOk()
            ->assertJsonPath('meta.enabled', false)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function malformed_date_filters_are_rejected(): void
    {
        $key = $this->auth();
        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/observations/prices?from=not-a-date')
            ->assertStatus(422);
    }

    #[Test]
    public function observation_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/observations/prices')->assertUnauthorized();
        $this->getJson('/api/v1/competitor-products')->assertUnauthorized();
        $this->getJson('/api/v1/competitor-products/1')->assertUnauthorized();
        $this->getJson('/api/v1/reviews')->assertUnauthorized();
    }
}
