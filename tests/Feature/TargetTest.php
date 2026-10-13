<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

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

    #[Test]
    public function it_stores_the_wanted_attributes_and_drops_them_when_a_later_post_omits_them(): void
    {
        $plaintext = $this->catalogWithProduct();
        $wanted = [
            ['key' => 'a12', 'label' => 'Made in', 'allowed_values' => ['Italia', 'Francia']],
            ['key' => 'a7', 'label' => 'Fascia Età', 'allowed_values' => ['Adulto', 'Bambino'], 'multiple' => false],
        ];

        $id = $this->withHeader('X-Api-Key', $plaintext)
            ->postJson('/api/v1/targets', ['product_external_id' => 'SKU-1', 'country' => 'IT', 'wanted_attributes' => $wanted])
            ->assertCreated()
            ->json('data.id');

        $options = MonitoringTarget::query()->findOrFail($id)->options;
        $this->assertSame(['key' => 'a12', 'label' => 'Made in', 'allowed_values' => ['Italia', 'Francia'], 'multiple' => false, 'jsonld_property' => null], $options['wanted_attributes'][0]);
        $this->assertCount(2, $options['wanted_attributes']);

        $this->withHeader('X-Api-Key', $plaintext)
            ->postJson('/api/v1/targets', ['product_external_id' => 'SKU-1', 'country' => 'IT'])
            ->assertCreated();

        $this->assertArrayNotHasKey('wanted_attributes', MonitoringTarget::query()->findOrFail($id)->options ?? []);
    }

    #[Test]
    public function it_validates_the_wanted_attributes(): void
    {
        $plaintext = $this->catalogWithProduct();
        $post = fn (array $wanted) => $this->withHeader('X-Api-Key', $plaintext)
            ->postJson('/api/v1/targets', ['product_external_id' => 'SKU-1', 'country' => 'IT', 'wanted_attributes' => $wanted]);

        $post([['key' => 'A-12', 'label' => 'Made in']])->assertUnprocessable()->assertJsonValidationErrors('wanted_attributes.0.key');
        $post([['key' => 'a1', 'label' => 'X'], ['key' => 'a1', 'label' => 'Y']])->assertUnprocessable()->assertJsonValidationErrors('wanted_attributes.0.key');
        $post([['key' => 'a1']])->assertUnprocessable()->assertJsonValidationErrors('wanted_attributes.0.label');
        $post([['key' => 'a1', 'label' => 'X', 'allowed_values' => ['Lana', 'lana ']]])->assertUnprocessable()->assertJsonValidationErrors('wanted_attributes.0.allowed_values');
        $post([['key' => 'a1', 'label' => 'X', 'allowed_values' => [str_repeat('x', 192)]]])->assertUnprocessable()->assertJsonValidationErrors('wanted_attributes.0.allowed_values.0');
        $post([['key' => 'a1', 'label' => 'X', 'allowed_values' => array_map(static fn (int $i): string => "v{$i}", range(1, 1001))]])
            ->assertUnprocessable()->assertJsonValidationErrors('wanted_attributes.0.allowed_values');
        $post(array_map(static fn (int $i): array => ['key' => "a{$i}", 'label' => 'X'], range(1, 51)))
            ->assertUnprocessable()->assertJsonValidationErrors('wanted_attributes');
        // 6 attributes x 1000 values breaks the 5000 total cap.
        $post(array_map(static fn (int $i): array => [
            'key' => "a{$i}", 'label' => 'X', 'allowed_values' => array_map(static fn (int $j): string => "v{$j}", range(1, 1000)),
        ], range(1, 6)))->assertUnprocessable()->assertJsonValidationErrors('wanted_attributes');
        // The same value may appear in two different attributes.
        $post([['key' => 'a1', 'label' => 'X', 'allowed_values' => ['Altro']], ['key' => 'a2', 'label' => 'Y', 'allowed_values' => ['Altro']]])->assertCreated();
    }

    private function catalogWithProduct(): string
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        [, $plaintext] = ApiKey::issue($tenant->id, 'test', ['*']);

        $this->withHeader('X-Api-Key', $plaintext)->postJson('/api/v1/catalog/products:bulk', ['products' => [
            ['external_id' => 'SKU-1', 'name' => 'Phone'],
        ]])->assertOk();

        return $plaintext;
    }
}
