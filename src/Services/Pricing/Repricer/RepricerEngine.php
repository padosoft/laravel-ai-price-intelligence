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

        if ($suggested === null) {
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
                'competitor_prices_cents' => array_values($competitorPricesCents),
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
        $registry = (array) config('price-intelligence.repricer.custom', []);
        $callable = $name !== null ? ($registry[$name] ?? null) : null;

        if (! is_callable($callable)) {
            return null;
        }

        $result = $callable($product, $competitorPricesCents, $current, $params);

        return is_int($result) ? $result : null;
    }
}
