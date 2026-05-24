<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Padosoft\PriceIntelligence\Enums\RuleStrategy;
use Padosoft\PriceIntelligence\Services\Pricing\Repricer\StrategyCalculator;

final class StrategyCalculatorTest extends TestCase
{
    private function calc(): StrategyCalculator
    {
        return new StrategyCalculator();
    }

    #[Test]
    public function match_cheapest_returns_lowest_competitor(): void
    {
        $this->assertSame(9000, $this->calc()->suggest(RuleStrategy::MatchCheapest, [9000, 9500, 11000], 10000));
    }

    #[Test]
    public function undercut_pct_goes_below_cheapest(): void
    {
        // 5% below 10000 = 9500.
        $this->assertSame(9500, $this->calc()->suggest(RuleStrategy::UndercutPct, [10000, 12000], 13000, ['undercut_pct' => 5]));
    }

    #[Test]
    public function beat_top_n_undercuts_the_average_of_cheapest(): void
    {
        // top_n=2 avg of [10000,12000]=11000; delta -10% -> 9900.
        $this->assertSame(9900, $this->calc()->suggest(RuleStrategy::BeatTopN, [10000, 12000, 20000], 15000, ['top_n' => 2, 'delta_pct' => -10]));
    }

    #[Test]
    public function margin_floor_is_respected(): void
    {
        // cheapest 5000 but floor 8000 -> 8000.
        $this->assertSame(8000, $this->calc()->suggest(RuleStrategy::MatchCheapest, [5000], 9000, ['min_price_cents' => 8000]));
    }

    #[Test]
    public function max_change_clamps_the_move(): void
    {
        // current 10000, cheapest 5000, max change 10% -> can't go below 9000.
        $this->assertSame(9000, $this->calc()->suggest(RuleStrategy::MatchCheapest, [5000], 10000, ['max_change_pct' => 10]));
    }

    #[Test]
    public function charm_rounding_applies(): void
    {
        // raw 9500 with charm .99 -> 9499 (9.99 ending, rounded down from 9500).
        $result = $this->calc()->suggest(RuleStrategy::MatchCheapest, [9500], 12000, ['round_to_charm' => 0.99]);
        $this->assertSame(9499, $result);
    }

    #[Test]
    public function no_competitors_returns_null(): void
    {
        $this->assertNull($this->calc()->suggest(RuleStrategy::MatchCheapest, [], 10000));
    }

    #[Test]
    public function no_change_when_suggestion_equals_current(): void
    {
        $this->assertNull($this->calc()->suggest(RuleStrategy::MatchCheapest, [10000], 10000));
    }

    #[Test]
    public function custom_strategy_returns_null_from_calculator(): void
    {
        // Custom is resolved by the engine, not the calculator.
        $this->assertNull($this->calc()->suggest(RuleStrategy::Custom, [9000], 10000));
    }
}
