<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithKey(string $code = 't1'): array
    {
        $tenant = Tenant::create(['code' => $code, 'name' => $code]);
        [$key, $plaintext] = ApiKey::issue($tenant->id, 'test', ['*']);

        return [$tenant, $plaintext];
    }

    #[Test]
    public function bulk_upsert_creates_and_updates_idempotently(): void
    {
        [$tenant, $key] = $this->tenantWithKey();

        $payload = ['products' => [
            ['external_id' => 'SKU-1', 'name' => 'Phone A', 'gtin' => '4006381333931', 'brand' => 'Acme'],
            ['external_id' => 'SKU-2', 'name' => 'Phone B'],
        ]];

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/catalog/products:bulk', $payload)
            ->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.updated', 0);

        // Re-send with one changed -> 0 created, 2 updated.
        $payload['products'][0]['name'] = 'Phone A v2';

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/catalog/products:bulk', $payload)
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 2);
    }

    #[Test]
    public function invalid_gtin_is_dropped_not_rejected(): void
    {
        [$tenant, $key] = $this->tenantWithKey();

        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/catalog/products:bulk', ['products' => [
                ['external_id' => 'SKU-1', 'name' => 'Bad GTIN', 'gtin' => '4006381333932'],
            ]])
            ->assertOk();

        app(TenantContext::class)->set($tenant->id);
        $this->assertNull(Product::query()->sole()->gtin);
    }

    #[Test]
    public function requests_without_api_key_are_unauthorized(): void
    {
        $this->postJson('/api/v1/catalog/products:bulk', ['products' => []])
            ->assertStatus(401);
    }

    #[Test]
    public function tenants_cannot_see_each_others_products(): void
    {
        [$t1, $key1] = $this->tenantWithKey('t1');
        [$t2, $key2] = $this->tenantWithKey('t2');

        $this->withHeader('X-Api-Key', $key1)->postJson('/api/v1/catalog/products:bulk', ['products' => [
            ['external_id' => 'A', 'name' => 'A'],
        ]])->assertOk();

        $this->withHeader('X-Api-Key', $key2)->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withHeader('X-Api-Key', $key1)->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
