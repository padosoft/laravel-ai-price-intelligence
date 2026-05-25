<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ExportApiTest extends TestCase
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
    public function catalog_exports_as_streamed_csv(): void
    {
        $key = $this->auth();
        Product::query()->create(['external_id' => 'A', 'name' => 'Alpha', 'currency' => 'EUR', 'our_price_cents' => 1000]);
        Product::query()->create(['external_id' => 'B', 'name' => 'Beta', 'currency' => 'EUR', 'our_price_cents' => 2000]);

        $response = $this->withHeader('X-Api-Key', $key)->get('/api/v1/catalog/products:export');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();
        $this->assertStringContainsString('external_id,sku,gtin,mpn,brand,name,our_price_cents,currency', $body);
        $this->assertStringContainsString('Alpha', $body);
        $this->assertStringContainsString('Beta', $body);
    }

    #[Test]
    public function price_observations_export_as_streamed_csv(): void
    {
        $key = $this->auth();
        PriceObservation::query()->create(['competitor_product_id' => 9, 'captured_at' => now(), 'price_cents' => 4242, 'currency' => 'EUR', 'price_base_cents' => 4242, 'available' => true]);

        $response = $this->withHeader('X-Api-Key', $key)->get('/api/v1/observations/prices:export?competitor_product_id=9');
        $response->assertOk();

        $body = $response->streamedContent();
        $this->assertStringContainsString('competitor_product_id,captured_at,price_cents', $body);
        $this->assertStringContainsString('4242', $body);
    }

    #[Test]
    public function export_requires_auth(): void
    {
        $this->get('/api/v1/catalog/products:export')->assertUnauthorized();
        $this->get('/api/v1/observations/prices:export')->assertUnauthorized();
    }
}
