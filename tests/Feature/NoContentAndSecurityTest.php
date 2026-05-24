<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Models\WebhookSubscription;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class NoContentAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function key(Tenant $tenant): string
    {
        [$k, $plain] = ApiKey::issue($tenant->id, 'k', ['*']);

        return $plain;
    }

    #[Test]
    public function delete_endpoints_return_204_no_content(): void
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);
        $product = Product::create(['external_id' => 'SKU-1', 'name' => 'P']);
        $key = $this->key($tenant);

        // Catalog destroy (regression: noContent() must not violate the return type).
        $this->withHeader('X-Api-Key', $key)
            ->deleteJson("/api/v1/catalog/products/{$product->id}")
            ->assertNoContent();
    }

    #[Test]
    public function webhook_secret_is_never_exposed_in_json(): void
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);
        $key = $this->key($tenant);

        $this->withHeader('X-Api-Key', $key)->postJson('/api/v1/webhook-subscriptions', [
            'url' => 'https://hook.test/in',
            'events' => ['*'],
            'secret' => 'topsecret',
        ])->assertCreated();

        $response = $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/webhook-subscriptions')
            ->assertOk();

        $raw = $response->getContent();
        $this->assertStringNotContainsString('secret_encrypted', $raw);
        $this->assertStringNotContainsString('topsecret', $raw);

        // But the secret is still usable internally (decrypts correctly).
        $this->assertSame('topsecret', WebhookSubscription::query()->sole()->secret);
    }
}
