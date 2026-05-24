<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\AssortmentGap;
use Padosoft\PriceIntelligence\Models\ContentGap;
use Padosoft\PriceIntelligence\Models\Narrative;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class NarrativeGapApiTest extends TestCase
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
    public function narratives_are_listed_by_period(): void
    {
        $key = $this->auth();
        Narrative::query()->create([
            'period' => '2026-W21',
            'summary_md' => '## Weekly digest\nPrices fell 3%.',
            'highlights' => ['top_movers' => 5],
            'is_ai_generated' => true,
            'generated_at' => now(),
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/narratives?period=2026-W21')
            ->assertOk()
            ->assertJsonPath('data.0.period', '2026-W21');
    }

    #[Test]
    public function assortment_gaps_are_ordered_by_importance(): void
    {
        $key = $this->auth();
        AssortmentGap::query()->create(['category_path' => 'tv', 'importance_score' => 30, 'status' => 'open']);
        AssortmentGap::query()->create(['category_path' => 'phones', 'importance_score' => 90, 'status' => 'open']);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/assortment-gaps?status=open')
            ->assertOk()
            ->assertJsonPath('data.0.category_path', 'phones');
    }

    #[Test]
    public function content_gaps_filter_by_product(): void
    {
        $key = $this->auth();
        ContentGap::query()->create([
            'product_id' => 42,
            'seo_score_delta' => -12,
            'missing_attributes' => ['weight', 'color'],
            'image_count_gap' => 3,
            'is_ai_generated' => true,
            'generated_at' => now(),
        ]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/content-gaps?product_id=42')
            ->assertOk()
            ->assertJsonPath('data.0.product_id', 42)
            ->assertJsonPath('data.0.image_count_gap', 3);
    }

    #[Test]
    public function endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/narratives')->assertUnauthorized();
        $this->getJson('/api/v1/assortment-gaps')->assertUnauthorized();
        $this->getJson('/api/v1/content-gaps')->assertUnauthorized();
    }
}
