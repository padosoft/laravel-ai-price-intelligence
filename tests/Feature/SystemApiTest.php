<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\FetchLog;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SystemApiTest extends TestCase
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
    public function rules_can_be_created_listed_updated_and_deleted(): void
    {
        $key = $this->auth();

        $id = $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/rules', [
                'name' => 'Beat Amazon -2%',
                'strategy' => 'beat_top_n',
                'parameters' => ['top_n' => 3, 'delta_pct' => -2, 'min_margin_pct' => 18],
                'priority' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Beat Amazon -2%')
            ->json('data.id');

        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/rules')
            ->assertOk()->assertJsonPath('data.0.id', $id);

        $this->withHeader('X-Api-Key', $key)->patchJson("/api/v1/rules/{$id}", ['status' => 'paused'])
            ->assertOk()->assertJsonPath('data.status', 'paused');

        $this->withHeader('X-Api-Key', $key)->deleteJson("/api/v1/rules/{$id}")->assertNoContent();
    }

    #[Test]
    public function invalid_strategy_is_rejected(): void
    {
        $key = $this->auth();
        $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/rules', ['name' => 'x', 'strategy' => 'nope'])
            ->assertStatus(422);
    }

    #[Test]
    public function api_keys_return_plaintext_once_and_can_be_revoked(): void
    {
        $key = $this->auth();

        $created = $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/api-keys', ['name' => 'CI bot', 'scopes' => ['catalog:read']])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'scopes', 'plaintext']]);
        $newId = $created->json('data.id');

        // The list never exposes the hash or plaintext.
        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/api-keys')
            ->assertOk()
            ->assertJsonMissing(['key_hash' => true]);

        $this->withHeader('X-Api-Key', $key)->deleteJson("/api/v1/api-keys/{$newId}")
            ->assertOk()->assertJsonPath('data.id', $newId);

        $this->assertNotNull(ApiKey::query()->find($newId)?->revoked_at);
    }

    #[Test]
    public function audit_fetch_logs_are_listed(): void
    {
        $key = $this->auth();
        FetchLog::query()->create([
            'url' => 'https://amazon.it/dp/X',
            'status' => 200,
            'latency_ms' => 312,
            'robots_allowed' => true,
            'driver' => 'generic',
            'captured_at' => now(),
        ]);

        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/audit/fetch-logs')
            ->assertOk()
            ->assertJsonPath('data.0.status', 200);
    }

    #[Test]
    public function api_keys_index_is_scoped_to_calling_tenant(): void
    {
        // Tenant A creates a key; Tenant B should not see it in the list.
        $tenantA = Tenant::create(['code' => 'ta', 'name' => 'A']);
        [, $keyA] = ApiKey::issue($tenantA->id, 'key-a', ['*']);

        $tenantB = Tenant::create(['code' => 'tb', 'name' => 'B']);
        [, $keyB] = ApiKey::issue($tenantB->id, 'key-b', ['*']);
        app(TenantContext::class)->set($tenantB->id);

        $this->withHeader('X-Api-Key', $keyB)
            ->getJson('/api/v1/api-keys')
            ->assertOk()
            ->assertJsonMissing(['name' => 'key-a']);
    }

    #[Test]
    public function api_key_revoke_cannot_target_another_tenants_key(): void
    {
        $tenantA = Tenant::create(['code' => 'ta2', 'name' => 'A2']);
        [$keyModelA] = ApiKey::issue($tenantA->id, 'victim', ['*']);

        $tenantB = Tenant::create(['code' => 'tb2', 'name' => 'B2']);
        [, $keyB] = ApiKey::issue($tenantB->id, 'attacker', ['*']);
        app(TenantContext::class)->set($tenantB->id);

        // Tenant B tries to revoke Tenant A's key by ID — must 404.
        $this->withHeader('X-Api-Key', $keyB)
            ->deleteJson("/api/v1/api-keys/{$keyModelA->id}")
            ->assertNotFound();

        // Confirm the target key was NOT revoked.
        $this->assertNull(ApiKey::query()->find($keyModelA->id)?->revoked_at);
    }

    #[Test]
    public function system_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/rules')->assertUnauthorized();
        $this->getJson('/api/v1/api-keys')->assertUnauthorized();
        $this->getJson('/api/v1/audit/fetch-logs')->assertUnauthorized();
    }
}
