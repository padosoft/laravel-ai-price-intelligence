<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Pricing\Repricer;

use Padosoft\PriceIntelligence\Enums\RuleStrategy;

/**
 * Pure price-strategy math. Given competitor prices, our current price and rule
 * parameters, computes a suggested price in cents (or null if no change is
 * warranted / there is not enough data). Applies a margin floor, a max daily
 * change clamp and charm rounding. NEVER mutates anything — advisory only.
 *
 * Recognized parameters (all optional):
 *  - top_n (int)               beat_top_n: how many cheapest competitors to average
 *  - delta_pct (float)         beat_top_n: adjust the reference by this % (negative = undercut)
 *  - undercut_pct (float)      undercut_pct: % below the cheapest competitor
 *  - min_price_cents (int)     hard price floor (margin protection)
 *  - max_change_pct (float)    clamp the move to ± this % of the current price
 *  - round_to_charm (float)    e.g. 0.99 -> round down to .99 endings (in major units)
 *  - demand_factor (float)     dynamic_demand: >1 more aggressive, <1 less
 */
final class StrategyCalculator
{
    /**
     * @param  array<int, int>  $competitorPricesCents
     * @param  array<string, mixed>  $params
     */
    public function suggest(RuleStrategy $strategy, array $competitorPricesCents, ?int $currentCents, array $params = []): ?int
    {
        $prices = array_values(array_filter(
            array_map('intval', $competitorPricesCents),
            static fn (int $p): bool => $p > 0,
        ));

        if ($prices === []) {
            return null;
        }

        sort($prices);
        $cheapest = $prices[0];

        $raw = match ($strategy) {
            RuleStrategy::MatchCheapest, RuleStrategy::MatchWithFloor => $cheapest,
            RuleStrategy::UndercutPct => (int) round($cheapest * (1 - $this->float($params, 'undercut_pct', 1) / 100)),
            RuleStrategy::BeatTopN => $this->beatTopN($prices, $params),
            RuleStrategy::DynamicDemand => (int) round($this->beatTopN($prices, $params) * $this->float($params, 'demand_factor', 1.0)),
            RuleStrategy::Custom => null, // resolved by a registered callable in RepricerEngine
        };

        if ($raw === null) {
            return null;
        }

        $suggested = $this->applyFloor($raw, $params);
        $suggested = $this->applyMaxChange($suggested, $currentCents, $params);
        $suggested = $this->applyCharm($suggested, $params);
        $suggested = max(1, $suggested);

        // No suggestion if it equals the current price.
        if ($currentCents !== null && $suggested === $currentCents) {
            return null;
        }

        return $suggested;
    }

    /**
     * @param  array<int, int>  $sortedPrices
     * @param  array<string, mixed>  $params
     */
    private function beatTopN(array $sortedPrices, array $params): int
    {
        $n = max(1, (int) ($params['top_n'] ?? 3));
        $top = array_slice($sortedPrices, 0, $n);
        $avg = array_sum($top) / count($top);
        $delta = $this->float($params, 'delta_pct', -2); // default undercut 2%

        return (int) round($avg * (1 + $delta / 100));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function applyFloor(int $price, array $params): int
    {
        $floor = isset($params['min_price_cents']) ? (int) $params['min_price_cents'] : null;

        return $floor !== null ? max($floor, $price) : $price;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function applyMaxChange(int $price, ?int $currentCents, array $params): int
    {
        if ($currentCents === null || ! isset($params['max_change_pct'])) {
            return $price;
        }

        $maxPct = $this->float($params, 'max_change_pct', 100);
        $low = (int) round($currentCents * (1 - $maxPct / 100));
        $high = (int) round($currentCents * (1 + $maxPct / 100));

        return min($high, max($low, $price));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function applyCharm(int $price, array $params): int
    {
        if (! isset($params['round_to_charm'])) {
            return $price;
        }

        $charm = $this->float($params, 'round_to_charm', 0.99); // major-unit ending
        $major = intdiv($price, 100);
        $candidate = $major * 100 + (int) round($charm * 100);

        // Round down to the charm ending; if that exceeds the price, drop one major unit.
        if ($candidate > $price) {
            $candidate -= 100;
        }

        return max(1, $candidate);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function float(array $params, string $key, float $default): float
    {
        return isset($params[$key]) && is_numeric($params[$key]) ? (float) $params[$key] : $default;
    }
}
