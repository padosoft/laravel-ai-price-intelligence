<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TenantSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function authTenant(): array
    {
        $tenant = Tenant::create(['code' => 't', 'name' => 'T']);
        [, $key] = ApiKey::issue($tenant->id, 'k', ['*']);
        app(TenantContext::class)->set($tenant->id);

        return [$tenant, $key];
    }

    #[Test]
    public function me_exposes_settings_defaulting_to_empty(): void
    {
        [, $key] = $this->authTenant();

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/tenants/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.settings', []);
    }

    #[Test]
    public function settings_can_be_patched_and_round_trip(): void
    {
        [$tenant, $key] = $this->authTenant();

        $this->withHeader('X-Api-Key', $key)
            ->patchJson('/api/v1/tenants/me/settings', ['settings' => ['currency_base' => 'EUR', 'digest_opt_in' => true]])
            ->assertOk()
            ->assertJsonPath('data.settings.currency_base', 'EUR')
            ->assertJsonPath('data.settings.digest_opt_in', true);

        $this->assertSame('EUR', $tenant->fresh()->settings['currency_base']);

        // A subsequent partial patch merges (does not clobber).
        $this->withHeader('X-Api-Key', $key)
            ->patchJson('/api/v1/tenants/me/settings', ['settings' => ['retention_days' => 120]])
            ->assertOk()
            ->assertJsonPath('data.settings.currency_base', 'EUR')
            ->assertJsonPath('data.settings.retention_days', 120);
    }

    #[Test]
    public function invalid_settings_body_is_rejected(): void
    {
        [, $key] = $this->authTenant();

        $this->withHeader('X-Api-Key', $key)
            ->patchJson('/api/v1/tenants/me/settings', ['settings' => 'not-an-array'])
            ->assertStatus(422);
    }

    #[Test]
    public function settings_update_requires_auth(): void
    {
        $this->patchJson('/api/v1/tenants/me/settings', ['settings' => []])->assertUnauthorized();
    }
}
