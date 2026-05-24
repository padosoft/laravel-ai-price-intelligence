<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Padosoft\PriceIntelligence\Contracts\RepricerEngineInterface;
use Padosoft\PriceIntelligence\Enums\RuleStrategy;
use Padosoft\PriceIntelligence\Events\RepricingSuggested;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\RepricingRule;
use Padosoft\PriceIntelligence\Models\RuleDecision;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;

final class RepricerEngineTest extends TestCase
{
    use RefreshDatabase;

    private function seedProductAndRule(): array
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);
        $product = Product::create(['external_id' => 'SKU-1', 'name' => 'Phone', 'our_price_cents' => 12000, 'currency' => 'EUR']);
        $rule = RepricingRule::create([
            'tenant_id' => $tenant->id, 'name' => 'beat', 'strategy' => RuleStrategy::MatchCheapest->value,
            'parameters' => [], 'status' => 'active',
        ]);

        return [$product, $rule];
    }

    #[Test]
    public function disabled_by_default_returns_null_and_persists_nothing(): void
    {
        config()->set('price-intelligence.repricer.enabled', false);
        [$product, $rule] = $this->seedProductAndRule();

        $this->assertNull(app(RepricerEngineInterface::class)->evaluate($product, $rule, [9000]));
        $this->assertSame(0, RuleDecision::query()->count());
    }

    #[Test]
    public function enabled_engine_suggests_persists_and_emits_event_without_applying(): void
    {
        Event::fake([RepricingSuggested::class]);
        config()->set('price-intelligence.repricer.enabled', true);
        [$product, $rule] = $this->seedProductAndRule();

        $decision = app(RepricerEngineInterface::class)->evaluate($product, $rule, [9000, 9500]);

        $this->assertNotNull($decision);
        $this->assertSame(12000, $decision->current_price_cents);
        $this->assertSame(9000, $decision->suggested_price_cents);
        $this->assertFalse($decision->applied); // NEVER applied by the package
        Event::assertDispatched(RepricingSuggested::class);
    }

    #[Test]
    public function custom_strategy_output_respects_the_margin_floor(): void
    {
        config()->set('price-intelligence.repricer.enabled', true);
        config()->set('price-intelligence.repricer.custom', [
            'too_low' => fn ($product, $prices, $current, $params): int => 1000,
        ]);
        [$product, $rule] = $this->seedProductAndRule();
        $rule->update([
            'strategy' => RuleStrategy::Custom->value,
            'parameters' => ['callable' => 'too_low', 'min_price_cents' => 8000],
        ]);

        $decision = app(RepricerEngineInterface::class)->evaluate($product->fresh(), $rule->fresh(), [9000]);

        $this->assertNotNull($decision);
        $this->assertGreaterThanOrEqual(8000, $decision->suggested_price_cents);
    }

    #[Test]
    public function custom_returning_current_price_produces_no_decision_or_event(): void
    {
        Event::fake([RepricingSuggested::class]);
        config()->set('price-intelligence.repricer.enabled', true);
        config()->set('price-intelligence.repricer.custom', [
            'same' => fn ($product, $prices, $current, $params): int => 12000, // equals current
        ]);
        [$product, $rule] = $this->seedProductAndRule();
        $rule->update(['strategy' => RuleStrategy::Custom->value, 'parameters' => ['callable' => 'same']]);

        $this->assertNull(app(RepricerEngineInterface::class)->evaluate($product->fresh(), $rule->fresh(), [9000]));
        $this->assertSame(0, RuleDecision::query()->count());
        Event::assertNotDispatched(RepricingSuggested::class);
    }

    #[Test]
    public function custom_strategy_resolves_from_container_binding(): void
    {
        // Recommended pattern: register in the container (config:cache safe).
        config()->set('price-intelligence.repricer.enabled', true);
        $this->app->bind('price-intelligence.repricer.custom.bound', fn () => fn ($product, $prices, $current, $params): int => 7777);
        [$product, $rule] = $this->seedProductAndRule();
        $rule->update(['strategy' => RuleStrategy::Custom->value, 'parameters' => ['callable' => 'bound']]);

        $decision = app(RepricerEngineInterface::class)->evaluate($product->fresh(), $rule->fresh(), [9000]);

        $this->assertNotNull($decision);
        $this->assertSame(7777, $decision->suggested_price_cents);
    }

    #[Test]
    public function custom_strategy_uses_registered_callable(): void
    {
        config()->set('price-intelligence.repricer.enabled', true);
        config()->set('price-intelligence.repricer.custom', [
            'flat_8888' => fn ($product, $prices, $current, $params): int => 8888,
        ]);
        [$product, $rule] = $this->seedProductAndRule();
        $rule->update(['strategy' => RuleStrategy::Custom->value, 'parameters' => ['callable' => 'flat_8888']]);

        $decision = app(RepricerEngineInterface::class)->evaluate($product->fresh(), $rule->fresh(), [9000]);

        $this->assertNotNull($decision);
        $this->assertSame(8888, $decision->suggested_price_cents);
    }
}
