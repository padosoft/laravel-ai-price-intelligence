<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Pricing\Repricer;

use Padosoft\PriceIntelligence\Contracts\RepricerEngineInterface;
use Padosoft\PriceIntelligence\Enums\RuleStrategy;
use Padosoft\PriceIntelligence\Events\RepricingSuggested;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\RepricingRule;
use Padosoft\PriceIntelligence\Models\RuleDecision;
use Padosoft\PriceIntelligence\Support\Config\Flag;

/**
 * Optional repricer. Disabled by default. Produces advisory suggestions only —
 * it NEVER writes a price back. When a suggestion is produced it persists a
 * RuleDecision (applied=false) and fires RepricingSuggested for the host to act on.
 */
final class RepricerEngine implements RepricerEngineInterface
{
    public function __construct(
        private readonly StrategyCalculator $calculator,
    ) {
    }

    /**
     * @param  array<int, int>  $competitorPricesCents
     */
    public function evaluate(Product $product, RepricingRule $rule, array $competitorPricesCents): ?RuleDecision
    {
        if (! Flag::enabled('price-intelligence.repricer.enabled', false) || ! $rule->isActive()) {
            return null;
        }

        $params = (array) ($rule->parameters ?? []);
        $current = $product->our_price_cents;

        $suggested = $rule->strategy === RuleStrategy::Custom
            ? $this->resolveCustom($rule, $product, $competitorPricesCents, $current, $params)
            : $this->calculator->suggest($rule->strategy, $competitorPricesCents, $current, $params);

        // No decision if there's nothing to do or it matches the current price
        // (uniform across all strategies, including custom).
        if ($suggested === null || $suggested === $current) {
            return null;
        }

        $decision = RuleDecision::query()->create([
            'tenant_id' => $rule->tenant_id,
            'repricing_rule_id' => $rule->id,
            'product_id' => $product->id,
            'current_price_cents' => $current,
            'suggested_price_cents' => $suggested,
            'applied' => false, // advisory only — the package never applies prices
            'reason' => $rule->strategy->value,
            'evidence' => [
                // Store the cleaned, sorted prices actually used by the calculator
                // (positive only) so the audit reflects what drove the decision.
                'competitor_prices_cents' => StrategyCalculator::cleanPrices($competitorPricesCents),
                'parameters' => $params,
            ],
        ]);

        RepricingSuggested::dispatch($decision);

        return $decision;
    }

    /**
     * Resolve a host-registered custom strategy closure from config.
     *
     * @param  array<int, int>  $competitorPricesCents
     * @param  array<string, mixed>  $params
     */
    private function resolveCustom(RepricingRule $rule, Product $product, array $competitorPricesCents, ?int $current, array $params): ?int
    {
        $name = is_string($params['callable'] ?? null) ? $params['callable'] : null;

        if ($name === null) {
            return null;
        }

        // Resolve the custom strategy from the CONTAINER (binding key
        // "price-intelligence.repricer.custom.{name}"), not from config: closures in
        // config break `php artisan config:cache`. Hosts register them in a service
        // provider. A config value that is a class-string is also supported.
        $callable = $this->resolveCustomCallable($name);

        if (! is_callable($callable)) {
            return null;
        }

        $result = $callable($product, $competitorPricesCents, $current, $params);

        if (! is_int($result)) {
            return null;
        }

        // Custom outputs go through the SAME safeguards (floor, max-change, charm)
        // so a host callable can't bypass margin protection.
        return $this->calculator->applyGuards($result, $current, $params);
    }

    private function resolveCustomCallable(string $name): mixed
    {
        $key = "price-intelligence.repricer.custom.{$name}";

        // 1) Container binding (recommended — register in a service provider).
        if (app()->bound($key)) {
            $resolved = app($key);

            return is_callable($resolved) ? $resolved : null;
        }

        // 2) Config value that is a class-string of an invokable (config-cache safe).
        $configured = config("price-intelligence.repricer.custom.{$name}");

        if (is_string($configured) && class_exists($configured)) {
            $instance = app($configured);

            return is_callable($instance) ? $instance : null;
        }

        // 3) A raw callable in config (works, but prevents config:cache — discouraged).
        return is_callable($configured) ? $configured : null;
    }
}
