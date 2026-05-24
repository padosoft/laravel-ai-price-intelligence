<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use Padosoft\PriceIntelligence\Enums\Frequency;
use Padosoft\PriceIntelligence\Services\Scheduling\AdaptiveBackoff;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdaptiveBackoffTest extends TestCase
{
    #[Test]
    public function stable_prices_slow_down_up_to_max(): void
    {
        $backoff = new AdaptiveBackoff(enabled: true, maxFactor: 4.0);

        $this->assertSame(2.0, $backoff->nextFactor(1.0, Frequency::Daily, 0.99, false));
        $this->assertSame(4.0, $backoff->nextFactor(3.0, Frequency::Daily, 0.99, false)); // capped
    }

    #[Test]
    public function significant_change_speeds_up(): void
    {
        $backoff = new AdaptiveBackoff;

        $this->assertSame(0.5, $backoff->nextFactor(4.0, Frequency::Daily, 0.99, true));
    }

    #[Test]
    public function weekly_never_slows_further(): void
    {
        $backoff = new AdaptiveBackoff;

        $this->assertSame(1.0, $backoff->nextFactor(2.0, Frequency::Weekly, 0.99, false));
    }

    #[Test]
    public function disabled_returns_neutral(): void
    {
        $backoff = new AdaptiveBackoff(enabled: false);

        $this->assertSame(1.0, $backoff->nextFactor(3.0, Frequency::Daily, 0.99, true));
    }

    #[Test]
    public function next_run_timestamp_applies_factor(): void
    {
        $backoff = new AdaptiveBackoff;
        $base = 1_000_000;

        // Daily = 86400s, factor 2 -> +172800.
        $this->assertSame($base + 172800, $backoff->nextRunTimestamp($base, Frequency::Daily, 2.0));
    }
}
