<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\CompetitorSource;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FacetApiTest extends TestCase
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
    public function host_facets_count_confirmed_competitors_per_host(): void
    {
        $key = $this->auth();
        $product = Product::query()->create(['external_id' => 'EX', 'name' => 'P', 'categories' => ['Electronics', 'Phones']]);
        $target = MonitoringTarget::query()->create(['product_id' => $product->id, 'country' => 'IT', 'status' => 'active', 'priority' => 1]);
        $amazon = CompetitorSource::query()->create(['host' => 'amazon.it', 'adapter_code' => 'generic', 'robots_policy' => 'respect']);
        $ebay = CompetitorSource::query()->create(['host' => 'ebay.it', 'adapter_code' => 'generic', 'robots_policy' => 'respect']);
        foreach ([$amazon, $amazon, $ebay] as $i => $src) {
            CompetitorProduct::query()->create([
                'monitoring_target_id' => $target->id,
                'competitor_source_id' => $src->id,
                'url' => 'https://'.$src->host.'/p'.$i,
                'match_status' => MatchStatus::Confirmed,
            ]);
        }

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/facets/hosts')
            ->assertOk()
            ->assertJsonPath('data.0.host', 'amazon.it')
            ->assertJsonPath('data.0.count', 2)
            ->assertJsonPath('data.1.host', 'ebay.it')
            ->assertJsonPath('data.1.count', 1);
    }

    #[Test]
    public function category_facets_count_products_per_category(): void
    {
        $key = $this->auth();
        Product::query()->create(['external_id' => 'A', 'name' => 'A', 'categories' => ['Electronics', 'Phones']]);
        Product::query()->create(['external_id' => 'B', 'name' => 'B', 'categories' => ['Electronics']]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/facets/categories')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'Electronics')
            ->assertJsonPath('data.0.count', 2);
    }

    #[Test]
    public function brand_facets_count_products_per_brand(): void
    {
        $key = $this->auth();
        Product::query()->create(['external_id' => 'A', 'name' => 'A', 'brand' => 'Acme']);
        Product::query()->create(['external_id' => 'B', 'name' => 'B', 'brand' => 'Acme']);
        Product::query()->create(['external_id' => 'C', 'name' => 'C', 'brand' => 'Nova']);
        // Both null and empty-string brands are excluded from the facet.
        Product::query()->create(['external_id' => 'D', 'name' => 'D', 'brand' => null]);
        Product::query()->create(['external_id' => 'E', 'name' => 'E', 'brand' => '']);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/facets/brands')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.brand', 'Acme')
            ->assertJsonPath('data.0.count', 2)
            ->assertJsonPath('data.1.brand', 'Nova')
            ->assertJsonPath('data.1.count', 1);
    }

    #[Test]
    public function facets_require_auth(): void
    {
        $this->getJson('/api/v1/facets/hosts')->assertUnauthorized();
        $this->getJson('/api/v1/facets/brands')->assertUnauthorized();
        $this->getJson('/api/v1/facets/categories')->assertUnauthorized();
    }
}
