<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TenantMeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_resolved_tenant_features_and_abilities(): void
    {
        $tenant = Tenant::create(['code' => 'acme', 'name' => 'Acme Italia']);
        [, $key] = ApiKey::issue($tenant->id, 'admin', ['catalog:read', 'matches:write']);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/tenants/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.code', 'acme')
            ->assertJsonPath('data.tenant.name', 'Acme Italia')
            ->assertJsonPath('data.abilities', ['catalog:read', 'matches:write'])
            ->assertJsonPath('data.features.repricer', false)
            ->assertJsonPath('data.features.review_insight', false);
    }

    #[Test]
    public function feature_flags_follow_core_config(): void
    {
        config()->set('price-intelligence.repricer.enabled', true);
        config()->set('price-intelligence.review_insight.enabled', true);
        config()->set('price-intelligence.ai_act.enabled', 'auto');

        $tenant = Tenant::create(['code' => 't', 'name' => 'T']);
        [, $key] = ApiKey::issue($tenant->id, 'k', ['*']);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/tenants/me')
            ->assertOk()
            ->assertJsonPath('data.features.repricer', true)
            ->assertJsonPath('data.features.review_insight', true)
            ->assertJsonPath('data.features.ai_act', true)
            ->assertJsonPath('data.abilities', ['*']);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/v1/tenants/me')->assertUnauthorized();
    }
}
