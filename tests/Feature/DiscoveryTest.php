<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Padosoft\LaravelAiSearchProviders\Models\SearchProviderConfig;
use Padosoft\PriceIntelligence\Enums\Frequency;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MatchProposal;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Discovery\UrlDiscoveryService;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;

final class DiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(array $webResults): void
    {
        SearchProviderConfig::query()->create([
            'code' => 'fake-web',
            'name' => 'Fake Web',
            'driver' => 'fake',
            'config' => ['web_results' => $webResults],
            'priority' => 1,
            'timeout_seconds' => 5,
            'is_active' => true,
        ]);
    }

    private function seedProductAndTarget(): MonitoringTarget
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $product = Product::create([
            'external_id' => 'SKU-1',
            'name' => 'Nike Air Force 1 White',
            'brand' => 'Nike',
            'model' => 'Air Force 1 White',
        ]);

        return MonitoringTarget::create([
            'product_id' => $product->id,
            'country' => 'IT',
            'locale' => 'it-IT',
            'frequency_preset' => Frequency::Daily,
            'status' => 'active',
            'next_check_at' => now(),
        ]);
    }

    #[Test]
    public function discovery_confirms_strong_match_and_queues_weak_one_for_review(): void
    {
        $this->fakeProvider([
            ['title' => 'Nike Air Force 1 White', 'page_url' => 'https://www.amazon.it/dp/B0AF1', 'image_url' => 'https://img/1.jpg'],
            ['title' => 'Nike Air Force 1 Mid Trail', 'page_url' => 'https://www.idealo.it/p/af1mid', 'image_url' => 'https://img/2.jpg'],
            ['title' => 'Samsung Galaxy S24 Ultra', 'page_url' => 'https://www.amazon.it/dp/BSAMS', 'image_url' => 'https://img/3.jpg'],
        ]);

        $target = $this->seedProductAndTarget();

        $stats = app(UrlDiscoveryService::class)->discover($target);

        $this->assertSame(3, $stats['candidates']);
        $this->assertGreaterThanOrEqual(1, $stats['confirmed']);

        $this->assertTrue(
            CompetitorProduct::query()->where('url', 'https://www.amazon.it/dp/B0AF1')->where('match_status', 'confirmed')->exists()
        );

        // The unrelated Samsung result must not be confirmed.
        $this->assertFalse(
            CompetitorProduct::query()->where('url', 'https://www.amazon.it/dp/BSAMS')->where('match_status', 'confirmed')->exists()
        );
    }

    #[Test]
    public function given_urls_are_auto_confirmed_without_search(): void
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $product = Product::create(['external_id' => 'SKU-9', 'name' => 'Whatever']);
        $target = MonitoringTarget::create([
            'product_id' => $product->id,
            'country' => 'IT',
            'frequency_preset' => Frequency::Daily,
            'status' => 'active',
            'options' => ['given_urls' => ['https://shop.example.it/p/123']],
            'next_check_at' => now(),
        ]);

        $stats = app(UrlDiscoveryService::class)->discover($target);

        $this->assertSame(1, $stats['confirmed']);
        $this->assertTrue(
            CompetitorProduct::query()->where('url', 'https://shop.example.it/p/123')
                ->where('match_status', 'confirmed')->where('match_method', 'manual')->exists()
        );
    }

    #[Test]
    public function admin_can_approve_a_proposal(): void
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);
        $product = Product::create(['external_id' => 'SKU-1', 'name' => 'Phone']);
        $target = MonitoringTarget::create([
            'product_id' => $product->id, 'country' => 'IT',
            'frequency_preset' => Frequency::Daily, 'status' => 'active',
        ]);

        $proposal = MatchProposal::create([
            'tenant_id' => $tenant->id,
            'monitoring_target_id' => $target->id,
            'candidate_url' => 'https://www.amazon.it/dp/B0XYZ',
            'confidence' => 72,
            'status' => 'pending',
        ]);

        [$key, $plaintext] = \Padosoft\PriceIntelligence\Models\ApiKey::issue($tenant->id, 'k', ['*']);

        $this->withHeader('X-Api-Key', $plaintext)
            ->postJson("/api/v1/matches/{$proposal->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.match_status', 'confirmed');

        $this->assertSame('approved', $proposal->fresh()->status);
    }
}
