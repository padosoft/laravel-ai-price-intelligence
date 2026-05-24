<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;

/**
 * Invokable tenant resolver used as a class-string (config:cache safe). Resolves
 * the tenant id from a property on the authenticated user.
 */
final class FixedTenantResolver
{
    public function __invoke(mixed $user): int|string|null
    {
        return $user->pi_tenant_id ?? null;
    }
}

final class TenantResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sanctum_user_tenant_is_resolved_via_class_string_resolver(): void
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);
        Product::create(['external_id' => 'SKU-1', 'name' => 'Phone']);
        app(TenantContext::class)->forget();

        config()->set('price-intelligence.api.tenant_resolver', FixedTenantResolver::class);

        // An authenticated user whose tenant the resolver reads (no X-Api-Key on the request).
        $user = new GenericUser(['id' => 1, 'pi_tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function request_without_api_key_or_user_is_unauthorized(): void
    {
        $this->getJson('/api/v1/catalog/products')->assertStatus(401);
    }
}
