<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Tests\TestCase;

final class TargetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_target_by_product_external_id(): void
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        [$key, $plaintext] = ApiKey::issue($tenant->id, 'test', ['*']);

        $this->withHeader('X-Api-Key', $plaintext)
            ->postJson('/api/v1/catalog/products:bulk', ['products' => [
                ['external_id' => 'SKU-1', 'name' => 'Phone'],
            ]])->assertOk();

        $this->withHeader('X-Api-Key', $plaintext)
            ->postJson('/api/v1/targets', [
                'product_external_id' => 'SKU-1',
                'country' => 'it',
                'frequency' => 'daily',
                'given_domains' => ['amazon.it'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.country', 'IT')
            ->assertJsonPath('data.frequency_preset', 'daily');
    }

    #[Test]
    public function it_pauses_a_target(): void
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        [$key, $plaintext] = ApiKey::issue($tenant->id, 'test', ['*']);

        $this->withHeader('X-Api-Key', $plaintext)->postJson('/api/v1/catalog/products:bulk', ['products' => [
            ['external_id' => 'SKU-1', 'name' => 'Phone'],
        ]])->assertOk();

        $created = $this->withHeader('X-Api-Key', $plaintext)->postJson('/api/v1/targets', [
            'product_external_id' => 'SKU-1', 'country' => 'IT',
        ])->json('data.id');

        $this->withHeader('X-Api-Key', $plaintext)
            ->patchJson("/api/v1/targets/{$created}", ['status' => 'paused'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paused');
    }
}
